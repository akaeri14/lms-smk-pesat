<?php

namespace App\Http\Controllers;

use App\Events\ScoreUpdated;
use App\Events\AnswerSubmitted;
use App\Events\QuizScoreUpdated;
use App\Events\QuizGameStateUpdated;
use App\Events\QuizStarted;
use App\Events\QuizEnded;
use App\Events\ParticipantJoined;
use App\Events\QuizTeamScoresUpdated;
use App\Events\CrownTransferred;
use App\Models\Quiz;
use App\Models\QuizAudioTrack;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizController extends Controller
{
    /**
     * Tampilkan daftar kuis milik kelas yang diikuti user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $quizzes = Quiz::query()
            ->with(['schoolClass:id,name,subject,teacher_id', 'creator:id,name'])
            ->withCount(['questions', 'attempts'])
            ->when($user->role === 'guru', fn ($q) => $q->whereHas('schoolClass', fn ($sq) => $sq->where('teacher_id', $user->id)))
            ->when($user->role === 'siswa', fn ($q) => $q
                ->whereHas('schoolClass.students', fn ($sq) => $sq->whereKey($user))
                ->with(['attempts' => fn ($aq) => $aq->where('user_id', $user->id)->latest()])
            )
            ->latest()
            ->get();

        $classes = ($user->role === 'guru' || $user->role === 'admin')
            ? SchoolClass::query()
                ->when($user->role === 'guru', fn ($q) => $q->where('teacher_id', $user->id))
                ->select('id', 'name', 'subject')
                ->get()
            : [];

        return Inertia::render('Quizzes/Index', [
            'quizzes' => $quizzes,
            'classes' => $classes,
            'canCreateQuiz' => $user->role === 'guru' || $user->role === 'admin',
        ]);
    }

    /**
     * Tampilkan form pembuatan kuis baru di dalam kelas.
     */
    public function create(Request $request, ?SchoolClass $schoolClass = null): Response
    {
        $user = $request->user();

        // Otorisasi: hanya guru atau admin yang bisa membuat kuis
        if ($user->role !== 'admin' && $user->role !== 'guru') {
            abort(403, 'Hanya guru yang dapat membuat kuis.');
        }

        $classes = SchoolClass::query()
            ->when($user->role === 'guru', fn ($q) => $q->where('teacher_id', $user->id))
            ->select('id', 'name', 'subject')
            ->get();

        if ($schoolClass && $user->role === 'guru' && $schoolClass->teacher_id !== $user->id) {
            abort(403, 'Hanya guru pengampu kelas yang dapat membuat kuis di kelas ini.');
        }

        $selectedClass = $schoolClass
            ? $schoolClass->only('id', 'name', 'subject')
            : ($classes->first() ? $classes->first()->only('id', 'name', 'subject') : null);

        $selectedGameMode = $request->input('game_mode', 'live_individual');
        if (! in_array($selectedGameMode, ['live_individual', 'assign', 'team_battle', 'steal_crown'], true)) {
            $selectedGameMode = 'live_individual';
        }

        return Inertia::render('Quizzes/Create', [
            'schoolClass' => $selectedClass,
            'classes' => $classes,
            'initialGameMode' => $selectedGameMode,
        ]);
    }

    /**
     * Simpan kuis beserta bank soal dan opsi jawaban ke database.
     */
    public function store(Request $request, ?SchoolClass $schoolClass = null): RedirectResponse
    {
        $user = $request->user();
        $classId = $schoolClass?->id ?? $request->input('class_id');
        $schoolClass = SchoolClass::findOrFail($classId);

        if ($user->role !== 'admin' && ($user->role !== 'guru' || $schoolClass->teacher_id !== $user->id)) {
            abort(403, 'Hanya guru pengampu kelas yang dapat membuat kuis.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'max_attempts' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'quiz_mode' => ['sometimes', 'in:live,assign'],
            'game_mode' => ['sometimes', 'in:live_individual,assign,team_battle,steal_crown'],
            'due_at' => ['nullable', 'date', 'required_if:quiz_mode,assign', 'after:now'],
            'randomize_options' => ['sometimes', 'boolean'],
            'bgm' => ['nullable', 'file', 'mimes:mp3', 'max:10240'],
            'time_per_question' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'enable_fifty_fifty' => ['sometimes', 'boolean'],
            'enable_freeze_time' => ['sometimes', 'boolean'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:2000'],
            'questions.*.question_type' => ['sometimes', 'in:multiple_choice_single,multiple_choice_multiple'],
            'questions.*.points' => ['required', 'integer', 'min:1', 'max:100'],
            'questions.*.time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'questions.*.options' => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*.option_text' => ['required', 'string', 'max:1000'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
        ], [
            'title.required' => 'Judul kuis wajib diisi.',
            'duration_minutes.required' => 'Durasi kuis wajib diisi.',
            'questions.required' => 'Kuis harus memiliki minimal 1 soal.',
            'questions.min' => 'Kuis harus memiliki minimal 1 soal.',
            'questions.*.question_text.required' => 'Teks pertanyaan wajib diisi.',
            'questions.*.options.min' => 'Setiap soal minimal harus memiliki 2 pilihan jawaban.',
        ]);

        // Pastikan setiap soal memiliki minimal 1 jawaban benar
        foreach ($validated['questions'] as $index => $questionData) {
            $correctCount = collect($questionData['options'])->where('is_correct', true)->count();
            $questionType = $questionData['question_type'] ?? 'multiple_choice_single';
            if ($correctCount < ($questionType === 'multiple_choice_multiple' ? 2 : 1)) {
                return back()->withErrors([
                    "questions.{$index}" => $questionType === 'multiple_choice_multiple'
                        ? "Soal nomor ".($index + 1)." harus memiliki minimal dua kunci jawaban yang benar."
                        : "Soal nomor ".($index + 1)." harus memiliki minimal satu kunci jawaban yang benar.",
                ]);
            }
        }

        $bgmPath = $request->file('bgm')?->store('quiz-bgm', 'public');
        DB::transaction(function () use ($schoolClass, $validated, $user, $bgmPath) {
            $quiz = $schoolClass->quizzes()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'duration_minutes' => $validated['duration_minutes'],
                'max_attempts' => $validated['max_attempts'] ?? null,
                'quiz_mode' => $validated['quiz_mode'] ?? 'live',
                'game_mode' => ($validated['quiz_mode'] ?? 'live') === 'assign' ? 'assign' : ($validated['game_mode'] ?? 'live_individual'),
                'due_at' => $validated['due_at'] ?? null,
                'randomize_options' => (bool) ($validated['randomize_options'] ?? true),
                'bgm_path' => $bgmPath,
                'time_per_question' => $validated['time_per_question'] ?? 30,
                'enable_fifty_fifty' => (bool) ($validated['enable_fifty_fifty'] ?? false),
                'enable_freeze_time' => (bool) ($validated['enable_freeze_time'] ?? false),
                'is_published' => true,
                'status' => 'published',
                'created_by' => $user->id,
            ]);

            foreach ($validated['questions'] as $qData) {
                $question = $quiz->questions()->create([
                    'question_text' => $qData['question_text'],
                    'question_type' => $qData['question_type'] ?? 'multiple_choice_single',
                    'points' => $qData['points'] ?? 10,
                    'time_limit_seconds' => $qData['time_limit_seconds'] ?? null,
                ]);

                foreach ($qData['options'] as $optData) {
                    $question->options()->create([
                        'option_text' => $optData['option_text'],
                        'is_correct' => (bool) $optData['is_correct'],
                    ]);
                }
            }
        });

        return redirect()->route('classes.show', $schoolClass->id)
            ->with('success', 'Game Kuis berhasil dibuat dan siap dimainkan!');
    }

    /**
     * Download template Excel untuk pengisian soal kuis.
     */
    public function downloadTemplate(Request $request): StreamedResponse
    {
        $gameMode = $request->input('game_mode', 'live_individual');
        $gameModeLabel = [
            'live_individual' => 'Live Host / Classic',
            'assign' => 'Assign (PR / Tugas)',
            'team_battle' => 'Team vs Team',
            'steal_crown' => 'Steal the Crown',
        ][$gameMode] ?? 'Live Host / Classic';
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Soal Kuis');

        $headers = [
            'Pertanyaan',
            'Tipe Soal',
            'Opsi A',
            'Opsi B',
            'Opsi C',
            'Opsi D',
            'Opsi E',
            'Jawaban Benar (A,C atau B,D)',
            'Durasi (detik)',
            'Poin',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $samples = [
            [
                'Tag HTML yang digunakan untuk membuat judul tingkat pertama adalah?',
                'pilihan_ganda',
                '<h1>',
                '<p>',
                '<title>',
                '<header>',
                '<h2>',
                'A',
                30,
                10,
            ],
            [
                'Protokol jaringan yang digunakan untuk mentransfer data halaman web secara terenkripsi/aman adalah?',
                'pilihan_ganda',
                'HTTP',
                'HTTPS',
                'FTP',
                'SMTP',
                '<h3>',
                'B',
                30,
                10,
            ],
            [
                'Manakah di bawah ini yang merupakan bahasa pemrograman sisi server (backend)?',
                'pilihan_ganda',
                'HTML',
                'CSS',
                'PHP',
                'XML',
                'kotlin',
                'C',
                30,
                10,
            ],
            [
                'Perangkat keras komputer yang berfungsi sebagai otak pemroses data utama adalah?',
                'pilihan_ganda',
                'RAM',
                'Hard Disk',
                'Power Supply',
                'CPU / Processor',
                'GPU',
                'D',
                30,
                10,
            ],
        ];
        $sheet->fromArray($samples, null, 'A2');

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $safeModeLabel = preg_replace('/[^A-Za-z0-9_-]+/', '_', $gameModeLabel);
        $fileName = 'Template_Soal_Kuis_SMKPesat_'.$safeModeLabel.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Import kuis beserta pertanyaan dan opsi jawaban dari file Excel/CSV.
     */
    public function import(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:180'],
            'quiz_mode' => ['sometimes', 'in:live,assign'],
            'game_mode' => ['sometimes', 'in:live_individual,assign,team_battle,steal_crown'],
            'max_attempts' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'due_at' => ['nullable', 'date', 'required_if:quiz_mode,assign', 'after:now'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'class_id.required' => 'Pilih kelas tujuan kuis terlebih dahulu.',
            'title.required' => 'Judul kuis wajib diisi.',
            'file.required' => 'File Excel/CSV soal kuis wajib diunggah.',
            'file.mimes' => 'Format file harus berupa Excel (.xlsx, .xls) atau CSV (.csv).',
            'file.max' => 'Ukuran file maksimal 10MB.',
        ]);

        $schoolClass = SchoolClass::findOrFail($validated['class_id']);

        if ($user->role !== 'admin' && ($user->role !== 'guru' || $schoolClass->teacher_id !== $user->id)) {
            abort(403, 'Anda tidak memiliki hak untuk membuat kuis di kelas ini.');
        }

        try {
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => 'Gagal membaca file Excel/CSV: '.$e->getMessage()]);
        }

        if (empty($rows) || count($rows) < 2) {
            return back()->withErrors(['file' => 'File Excel kosong atau tidak memiliki baris data soal.']);
        }

        // Parse format baru berdasarkan header; format template lama tetap didukung.
        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), $rows[0] ?? []);
        $newFormat = in_array('tipe soal', $headers, true);
        $column = function (string $name, int $fallback) use ($headers): int {
            $index = array_search(strtolower($name), $headers, true);
            return $index === false ? $fallback : $index;
        };
        $questionColumn = $column('pertanyaan', 0);
        $typeColumn = $column('tipe soal', 1);
        $optionColumns = [$column('opsi a', $newFormat ? 2 : 1), $column('opsi b', $newFormat ? 3 : 2), $column('opsi c', $newFormat ? 4 : 3), $column('opsi d', $newFormat ? 5 : 4)];
        $answerColumn = $column('jawaban benar (a,c atau b,d)', $newFormat ? 6 : 5);
        if ($newFormat && $answerColumn === 6 && ! in_array('jawaban benar (a,c atau b,d)', $headers, true)) {
            $answerColumn = $column('kunci jawaban (a/b/c/d)', 6);
        }
        $durationColumn = $column('durasi (detik)', $newFormat ? 7 : 6);
        $pointsColumn = $column('poin', $newFormat ? 8 : 7);

        $parsedQuestions = [];
        $totalDurationSeconds = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $questionText = trim((string) ($row[$questionColumn] ?? ''));
            if ($questionText === '') {
                continue;
            }

            $optionValues = array_map(fn (int $index) => trim((string) ($row[$index] ?? '')), $optionColumns);
            $typeValue = strtolower(trim((string) ($row[$typeColumn] ?? 'pilihan_ganda')));
            $questionType = in_array($typeValue, ['multiple_choice_multiple', 'pilihan_ganda_kompleks', 'kompleks'], true)
                ? 'multiple_choice_multiple' : 'multiple_choice_single';
            $correctKeys = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;|]/', strtoupper((string) ($row[$answerColumn] ?? 'A')))))));
            $durationSec = (int) ($row[$durationColumn] ?? 30);
            $points = (int) ($row[$pointsColumn] ?? 10);
            if ($points <= 0) {
                $points = 10;
            }

            $totalDurationSeconds += $durationSec > 0 ? $durationSec : 30;

            $options = [];
            foreach ($optionValues as $index => $value) {
                if ($value !== '') {
                    $options[] = ['text' => $value, 'is_correct' => in_array(chr(65 + $index), $correctKeys, true)];
                }
            }

            if (count($options) < 2) {
                continue;
            }

            // Pastikan ada setidaknya 1 opsi jawaban benar
            if ($questionType === 'multiple_choice_multiple' && collect($options)->where('is_correct', true)->count() < 2) {
                continue;
            }
            if (! collect($options)->contains(fn ($o) => $o['is_correct'])) {
                $options[0]['is_correct'] = true;
            }

            $parsedQuestions[] = [
                'question_text' => $questionText,
                'question_type' => $questionType,
                'points' => $points,
                'time_limit_seconds' => $durationSec > 0 ? min(120, max(5, $durationSec)) : null,
                'options' => $options,
            ];
        }

        if (empty($parsedQuestions)) {
            return back()->withErrors(['file' => 'Tidak ditemukan baris pertanyaan yang valid di dalam file. Silakan periksa kembali template Excel Anda.']);
        }

        $durationMinutes = ! empty($validated['duration_minutes'])
            ? (int) $validated['duration_minutes']
            : max(1, (int) ceil($totalDurationSeconds / 60));
        $gameMode = $validated['game_mode'] ?? (($validated['quiz_mode'] ?? 'live') === 'assign' ? 'assign' : 'live_individual');

        DB::transaction(function () use ($schoolClass, $validated, $user, $durationMinutes, $parsedQuestions, $gameMode) {
            $quiz = $schoolClass->quizzes()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'duration_minutes' => $durationMinutes,
                'max_attempts' => $validated['max_attempts'] ?? null,
                'quiz_mode' => $validated['quiz_mode'] ?? ($gameMode === 'assign' ? 'assign' : 'live'),
                'game_mode' => $gameMode,
                'due_at' => $validated['due_at'] ?? null,
                'time_per_question' => 30,
                'randomize_options' => true,
                'enable_fifty_fifty' => true,
                'enable_freeze_time' => true,
                'is_published' => true,
                'status' => 'published',
                'created_by' => $user->id,
            ]);

            foreach ($parsedQuestions as $qData) {
                $question = $quiz->questions()->create([
                    'question_text' => $qData['question_text'],
                    'question_type' => $qData['question_type'],
                    'points' => $qData['points'],
                    'time_limit_seconds' => $qData['time_limit_seconds'] ?? null,
                ]);

                foreach ($qData['options'] as $opt) {
                    $question->options()->create([
                        'option_text' => $opt['text'],
                        'is_correct' => $opt['is_correct'],
                    ]);
                }
            }
        });

        $count = count($parsedQuestions);

        return back()->with('success', "Berhasil mengimpor kuis '{$validated['title']}' dengan {$count} soal dari Excel!");
    }

    /**
     * Tampilkan detail kuis, statistik, dan leaderboard kelas.
     */
    public function show(Request $request, Quiz $quiz): Response
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;

        // Otorisasi akses kuis
        $isTeacher = $user->role === 'admin' || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id);
        $isStudent = $schoolClass->students()->whereKey($user->id)->exists();

        if (! $isTeacher && ! $isStudent) {
            abort(403, 'Anda tidak terdaftar di kelas kuis ini.');
        }

        $quiz->load(['schoolClass:id,name,subject,teacher_id', 'creator:id,name']);
        $quiz->loadCount('questions');

        // Leaderboard kuis (skor tertinggi)
        $leaderboard = $quiz->attempts()
            ->with('user:id,name,avatar')
            ->orderByDesc('dynamic_score')
            ->orderByDesc('score')
            ->orderBy('completed_at')
            ->take(5)
            ->get();

        // Riwayat pengerjaan user saat ini
        $myAttempt = $quiz->attempts()
            ->where('user_id', $user->id)
            ->latest('completed_at')
            ->first();

        return Inertia::render('Quizzes/Show', [
            'quiz' => $quiz,
            'canManage' => $isTeacher,
            'leaderboard' => $leaderboard,
            'myAttempt' => $myAttempt,
        ]);
    }

    /**
     * Tampilkan form edit kuis (Guru/Admin).
     */
    public function edit(Request $request, Quiz $quiz): Response
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;

        if ($user->role !== 'admin' && ($user->role !== 'guru' || $schoolClass->teacher_id !== $user->id)) {
            abort(403, 'Anda tidak memiliki hak untuk mengedit kuis ini.');
        }

        $quiz->load([
            'schoolClass:id,name,subject,teacher_id',
            'questions.options',
        ]);
        $quiz->loadCount('questions');

        return Inertia::render('Quizzes/Edit', [
            'quiz'        => $quiz,
            'schoolClass' => $quiz->schoolClass->only('id', 'name', 'subject'),
        ]);
    }

    /**
     * Simpan perubahan kuis beserta bank soal yang diperbarui.
     */
    public function update(Request $request, Quiz $quiz): RedirectResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;

        if ($user->role !== 'admin' && ($user->role !== 'guru' || $schoolClass->teacher_id !== $user->id)) {
            abort(403, 'Anda tidak memiliki hak untuk memperbarui kuis ini.');
        }

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:180'],
            'max_attempts' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:20'],
            'quiz_mode' => ['sometimes', 'in:live,assign'],
            'game_mode' => ['sometimes', 'in:live_individual,assign,team_battle,steal_crown'],
            'due_at' => ['nullable', 'date', 'required_if:quiz_mode,assign', 'after:now'],
            'randomize_options' => ['sometimes', 'boolean'],
            'bgm' => ['nullable', 'file', 'mimes:mp3', 'max:10240'],
            'time_per_question' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'enable_fifty_fifty' => ['sometimes', 'boolean'],
            'enable_freeze_time' => ['sometimes', 'boolean'],
            'questions'        => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:2000'],
            'questions.*.question_type' => ['sometimes', 'in:multiple_choice_single,multiple_choice_multiple'],
            'questions.*.points'        => ['required', 'integer', 'min:1', 'max:100'],
            'questions.*.time_limit_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'questions.*.options'       => ['required', 'array', 'min:2', 'max:6'],
            'questions.*.options.*.option_text' => ['required', 'string', 'max:1000'],
            'questions.*.options.*.is_correct'  => ['required', 'boolean'],
        ], [
            'title.required'      => 'Judul kuis wajib diisi.',
            'questions.required'  => 'Kuis harus memiliki minimal 1 soal.',
            'questions.*.question_text.required' => 'Teks pertanyaan wajib diisi.',
            'questions.*.options.min' => 'Setiap soal minimal memiliki 2 pilihan jawaban.',
        ]);

        // Pastikan setiap soal punya minimal 1 jawaban benar
        foreach ($validated['questions'] as $index => $questionData) {
            $correctCount = collect($questionData['options'])->where('is_correct', true)->count();
            $questionType = $questionData['question_type'] ?? 'multiple_choice_single';
            if ($correctCount < ($questionType === 'multiple_choice_multiple' ? 2 : 1)) {
                return back()->withErrors([
                    "questions.{$index}" => $questionType === 'multiple_choice_multiple'
                        ? 'Soal nomor '.($index + 1).' harus memiliki minimal dua kunci jawaban yang benar.'
                        : 'Soal nomor '.($index + 1).' harus memiliki minimal satu kunci jawaban yang benar.',
                ]);
            }
        }

        $bgmPath = $request->file('bgm')?->store('quiz-bgm', 'public');
        DB::transaction(function () use ($quiz, $validated, $bgmPath) {
            $quiz->update([
                'title'            => $validated['title'],
                'description'      => $validated['description'] ?? null,
                'duration_minutes' => $validated['duration_minutes'],
                'max_attempts' => $validated['max_attempts'] ?? null,
                'quiz_mode' => $validated['quiz_mode'] ?? 'live',
                'game_mode' => ($validated['quiz_mode'] ?? 'live') === 'assign' ? 'assign' : ($validated['game_mode'] ?? 'live_individual'),
                'due_at' => $validated['due_at'] ?? null,
                'randomize_options' => (bool) ($validated['randomize_options'] ?? true),
                ...($bgmPath ? ['bgm_path' => $bgmPath] : []),
                'time_per_question' => $validated['time_per_question'] ?? 30,
                'enable_fifty_fifty' => (bool) ($validated['enable_fifty_fifty'] ?? false),
                'enable_freeze_time' => (bool) ($validated['enable_freeze_time'] ?? false),
            ]);

            // Hapus semua soal dan opsi lama, lalu buat ulang
            $quiz->questions()->each(fn ($q) => $q->options()->delete());
            $quiz->questions()->delete();

            foreach ($validated['questions'] as $qData) {
                $question = $quiz->questions()->create([
                    'question_text' => $qData['question_text'],
                    'question_type' => $qData['question_type'] ?? 'multiple_choice_single',
                    'points'        => $qData['points'] ?? 10,
                    'time_limit_seconds' => $qData['time_limit_seconds'] ?? null,
                ]);

                foreach ($qData['options'] as $optData) {
                    $question->options()->create([
                        'option_text' => $optData['option_text'],
                        'is_correct'  => (bool) $optData['is_correct'],
                    ]);
                }
            }
        });

        return redirect()->route('quizzes.show', $quiz->id)
            ->with('success', 'Kuis berhasil diperbarui!');
    }

    /**
     * Halaman Gamifikasi Interaktif Pengerjaan Kuis (Ala Quizizz / Wayground).
     */
    public function play(Request $request, Quiz $quiz): Response|RedirectResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;

        $isTeacher = $user->role === 'admin' || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id);
        $isStudent = $schoolClass->students()->whereKey($user->id)->exists();

        if (! $isTeacher && ! $isStudent) {
            abort(403, 'Anda tidak terdaftar di kelas kuis ini.');
        }

        $quiz->load(['schoolClass:id,name,subject']);
        $gameState = $this->quizGameState($quiz->id);
        $gameState = $this->ensureTeamState($quiz, $gameState);
        $gameState = $this->ensureCrownState($quiz, $gameState);

        if ($isStudent && $quiz->quiz_mode === 'live' && $gameState['status'] === 'ended') {
            return redirect()->route('quizzes.results', $quiz->id);
        }

        if ($isStudent && $quiz->quiz_mode === 'assign' && $quiz->due_at?->isPast()) {
            abort(403, 'Tenggat waktu kuis ini telah berakhir.');
        }

        if ($quiz->quiz_mode === 'assign') {
            $gameState['status'] = 'running';
        }
        $myParticipant = $gameState['participants'][(string) $user->id] ?? null;
        $myTeam = $myParticipant && $quiz->game_mode === 'team_battle'
            ? collect($gameState['teams'] ?? [])->firstWhere('id', $myParticipant['team_id'] ?? null)
            : null;

        // Load questions with options.
        // PENTING: Untuk mencegah kecurangan inspect element, sembunyikan kolom is_correct saat kuis dimainkan!
        $questions = $quiz->questions()
            ->with(['options' => fn ($query) => $query->select('id', 'quiz_question_id', 'option_text', 'is_correct')])
            ->get();

        $questions->each(function ($question) use ($quiz): void {
            $question->setAttribute(
                'fifty_fifty_remove_ids',
                $question->options->where('is_correct', false)->shuffle()->take(2)->pluck('id')->values()->all()
            );
            if ($quiz->randomize_options ?? true) {
                $question->setRelation('options', $question->options->shuffle()->values());
            }
            $question->setRelation('options', $question->options->makeHidden('is_correct'));
        });

        // Riwayat attempt siswa jika sudah pernah mengerjakan
        $myAttempt = $quiz->attempts()
            ->where('user_id', $user->id)
            ->latest('completed_at')
            ->first();

        // Leaderboard untuk ditampilkan di akhir pengerjaan
        $attemptsUsed = $quiz->attempts()->where('user_id', $user->id)->count();
        $canAttempt = $user->role === 'admin'
            || $quiz->max_attempts === null
            || $attemptsUsed < $quiz->max_attempts;

        $leaderboard = $quiz->attempts()
            ->with('user:id,name,avatar')
            ->orderByDesc('dynamic_score')
            ->orderByDesc('score')
            ->orderBy('completed_at')
            ->take(5)
            ->get();

        return Inertia::render('Quizzes/Play', [
            'quiz' => $quiz,
            'questions' => $questions,
            'myAttempt' => $myAttempt,
            'attemptsUsed' => $attemptsUsed,
            'canAttempt' => $canAttempt,
            'liveLeaderboard' => $this->liveLeaderboardData($quiz->id),
            'gameState' => $gameState,
            'leaderboard' => $leaderboard,
            'classAccuracy' => $this->liveAccuracy($quiz->id),
            'myTeam' => $myTeam,
            'crown' => [
                'holder_id' => $gameState['crown_holder_id'] ?? null,
                'holder_name' => $gameState['crown_holder_name'] ?? null,
                'score' => (int) ($gameState['crown_holder_score'] ?? 0),
            ],
            'myCrown' => ($gameState['crown_holder_id'] ?? null) == $user->id,
            'teamScores' => $gameState['teams'] ?? [],
            'audioTracks' => QuizAudioTrack::query()->where('is_active', true)->orderBy('sort_order')->orderBy('title')->get(['id', 'title', 'path', 'is_active', 'sort_order']),
        ]);
    }

    /** Ringkasan Game Over untuk siswa setelah Host mengakhiri Live Game. */
    public function results(Request $request, Quiz $quiz): Response
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;
        $isTeacher = $user->role === 'admin' || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id);
        $isStudent = $schoolClass->students()->whereKey($user->id)->exists();

        abort_unless($isTeacher || $isStudent, 403, 'Anda tidak terdaftar di kelas kuis ini.');

        $quiz->load(['schoolClass:id,name,subject']);
        $latestAttempts = $quiz->attempts()
            ->with('user:id,name,avatar')
            ->latest('completed_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($attempts) => $attempts->sortByDesc(fn ($attempt) => [$attempt->dynamic_score, $attempt->score])->first())
            ->values();

        $leaderboard = $latestAttempts
            ->map(fn ($attempt) => [
                'user_id' => $attempt->user_id,
                'name' => $attempt->user?->name ?? 'Peserta',
                'score' => (int) ($attempt->dynamic_score ?: $attempt->score),
                'accuracy' => $attempt->total_questions > 0
                    ? round(($attempt->total_correct / $attempt->total_questions) * 100, 1)
                    : 0,
            ])
            ->sortByDesc('score')
            ->values();

        // Sertakan skor sementara siswa yang belum sempat menekan submit saat game diakhiri.
        foreach ($this->liveLeaderboardData($quiz->id) as $live) {
            if (! $leaderboard->contains(fn (array $item): bool => (int) $item['user_id'] === (int) $live['user_id'])) {
                $liveState = Cache::get("quiz:live:{$quiz->id}:user:{$live['user_id']}", []);
                $answers = collect($liveState['answers'] ?? []);
                $leaderboard->push([
                    'user_id' => $live['user_id'],
                    'name' => $live['name'],
                    'score' => (int) $live['current_score'],
                    'accuracy' => $answers->isNotEmpty()
                        ? round(($answers->where('correct', true)->count() / $answers->count()) * 100, 1)
                        : 0,
                ]);
            }
        }

        $leaderboard = $leaderboard->sortByDesc('score')->values()->map(function (array $item, int $index): array {
            return [...$item, 'rank' => $index + 1];
        });
        $myEntry = $leaderboard->firstWhere('user_id', $user->id);
        $totalQuestions = $quiz->questions()->count();
        $correct = (int) ($myEntry['accuracy'] ?? 0) > 0
            ? (int) round(($myEntry['accuracy'] / 100) * $totalQuestions)
            : 0;
        $myAttempt = $quiz->attempts()->where('user_id', $user->id)->latest('completed_at')->first();

        return Inertia::render('Quizzes/Results', [
            'quiz' => $quiz,
            'leaderboard' => $leaderboard->take(5)->values(),
            'podium' => $leaderboard->take(3)->values(),
            'myResult' => [
                'rank' => $myEntry['rank'] ?? null,
                'score' => $myAttempt?->score ?? ($myEntry['score'] ?? 0),
                'correct' => $myAttempt?->total_correct ?? $correct,
                'incorrect' => $myAttempt
                    ? max(0, $myAttempt->total_questions - $myAttempt->total_correct)
                    : max(0, $totalQuestions - $correct),
                'accuracy' => $myEntry['accuracy'] ?? 0,
            ],
        ]);
    }

    /** Host view lobby dan dashboard permainan.
     */
    public function host(Request $request, Quiz $quiz): Response
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;
        $canHost = $user->role === 'admin' || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id);

        abort_unless($canHost, 403, 'Hanya guru pengampu atau admin yang dapat menjadi host kuis.');

        $quiz->load(['schoolClass:id,name,subject']);
        $gameState = $this->quizGameState($quiz->id);
        $gameState = $this->ensureCrownState($quiz, $gameState);
        if ($gameState['status'] === 'ended') {
            $gameState = ['status' => 'waiting', 'participants' => [], 'started_at' => null];
            $this->saveQuizGameState($quiz->id, $gameState);
            Cache::forget("quiz:live:{$quiz->id}:leaderboard");
        }
        $gameState = $this->ensureTeamState($quiz, $gameState);
        $gameState = $this->ensureCrownState($quiz, $gameState);
        $this->saveQuizGameState($quiz->id, $gameState);

        return Inertia::render('Quizzes/Host', [
            'quiz' => $quiz,
            'gameState' => $gameState,
            'liveLeaderboard' => $this->liveLeaderboardData($quiz->id),
            'classAccuracy' => $this->liveAccuracy($quiz->id),
            'audioTracks' => QuizAudioTrack::query()->where('is_active', true)->orderBy('sort_order')->orderBy('title')->get(['id', 'title', 'path', 'is_active', 'sort_order']),
        ]);
    }

    public function joinGame(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;
        $isAllowed = $user->role === 'admin' || $schoolClass->students()->whereKey($user->id)->exists();

        abort_unless($isAllowed, 403, 'Anda tidak terdaftar di kelas kuis ini.');

        $state = $this->quizGameState($quiz->id);
        $state = $this->ensureTeamState($quiz, $state);
        $state = $this->ensureCrownState($quiz, $state);
        if ($quiz->quiz_mode === 'assign') {
            $state['status'] = 'running';
        }
        $participant = [
            'user_id' => $user->id,
            'name' => $user->name,
            'joined_at' => now()->toISOString(),
        ];
        if ($quiz->game_mode === 'team_battle') {
            $team = collect($state['teams'])->sortBy(fn (array $item) => count($item['member_ids'] ?? []))->first();
            if ($team) {
                $participant['team_id'] = $team['id'];
                $participant['team_name'] = $team['name'];
                $participant['team_color'] = $team['color'];
                foreach ($state['teams'] as &$teamState) {
                    if ($teamState['id'] === $team['id']) $teamState['member_ids'][] = $user->id;
                }
                unset($teamState);
            }
        }
        $state['participants'][(string) $user->id] = $participant;
        $this->saveQuizGameState($quiz->id, $state);
        $this->broadcastGameState($quiz->id, $state, 'Siswa bergabung ke lobby.');
        try {
            event(new ParticipantJoined($quiz->id, $state['participants'][(string) $user->id], count($state['participants'])));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json($state);
    }

    public function shuffleTeams(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $this->authorizeQuizHost($request, $quiz);
        abort_unless($quiz->game_mode === 'team_battle', 422, 'Mode kuis ini bukan Team vs Team.');
        $validated = $request->validate(['team_count' => ['required', 'integer', 'in:2,3,4']]);
        $state = $this->ensureTeamState($quiz, $this->quizGameState($quiz->id), (int) $validated['team_count']);
        foreach ($state['teams'] as &$team) {
            $team['member_ids'] = [];
            $team['score'] = 0;
            $team['correct_answers'] = 0;
            $team['answered_questions'] = 0;
        }
        unset($team);
        $participants = collect($state['participants'])->shuffle()->values();
        foreach ($participants as $index => $participant) {
            $team = $state['teams'][$index % count($state['teams'])];
            $participant['team_id'] = $team['id'];
            $participant['team_name'] = $team['name'];
            $participant['team_color'] = $team['color'];
            $state['participants'][(string) $participant['user_id']] = $participant;
            $state['teams'][$index % count($state['teams'])]['member_ids'][] = $participant['user_id'];
        }
        $this->saveQuizGameState($quiz->id, $state);
        $this->broadcastGameState($quiz->id, $state, 'Pembagian tim diperbarui.');
        try {
            event(new QuizTeamScoresUpdated($quiz->id, $state['teams']));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return response()->json($state);
    }

    public function gameState(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;
        $isAllowed = $user->role === 'admin'
            || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id)
            || $schoolClass->students()->whereKey($user->id)->exists();

        abort_unless($isAllowed, 403, 'Anda tidak memiliki akses ke game ini.');

        return response()->json($this->quizGameState($quiz->id));
    }

    public function startGame(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $this->authorizeQuizHost($request, $quiz);
        abort_unless($quiz->quiz_mode === 'live', 422, 'Hanya kuis Live Game yang dapat dimulai oleh host.');
        $state = $this->quizGameState($quiz->id);
        $state = $this->ensureTeamState($quiz, $state);
        $state = $this->ensureCrownState($quiz, $state);
        if ($quiz->game_mode === 'team_battle' && collect($state['participants'])->contains(fn (array $participant): bool => empty($participant['team_id']))) {
            $state = $this->shuffleTeamState($state);
        }
        $state['status'] = 'running';
        $state['started_at'] = now()->toISOString();
        $this->saveQuizGameState($quiz->id, $state);
        $this->broadcastGameState($quiz->id, $state, 'Kuis dimulai oleh guru.');
        $this->broadcastQuizStarted($quiz->id, $state);

        return response()->json($state);
    }

    public function pauseGame(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $this->authorizeQuizHost($request, $quiz);
        $state = $this->quizGameState($quiz->id);
        $state['status'] = $state['status'] === 'paused' ? 'running' : 'paused';
        $this->saveQuizGameState($quiz->id, $state);
        $this->broadcastGameState($quiz->id, $state, $state['status'] === 'paused' ? 'Kuis dijeda.' : 'Kuis dilanjutkan.');

        return response()->json($state);
    }

    public function endGame(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $this->authorizeQuizHost($request, $quiz);
        $state = $this->quizGameState($quiz->id);
        $state['status'] = 'ended';
        $this->saveQuizGameState($quiz->id, $state);
        $this->broadcastGameState($quiz->id, $state, 'Kuis diakhiri oleh guru.');
        $this->broadcastQuizEnded($quiz->id);

        return response()->json($state);
    }

    private function authorizeQuizHost(Request $request, Quiz $quiz): void
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;
        abort_unless($user->role === 'admin' || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id), 403);
    }

    private function quizGameState(int $quizId): array
    {
        return Cache::get("quiz:game:{$quizId}", [
            'status' => 'waiting',
            'participants' => [],
            'started_at' => null,
        ]);
    }

    private function ensureTeamState(Quiz $quiz, array $state, ?int $teamCount = null): array
    {
        if ($quiz->game_mode !== 'team_battle') return $state;
        $teamCount ??= (int) ($state['team_count'] ?? 2);
        $teamCount = max(2, min(4, $teamCount));
        if (($state['team_count'] ?? null) !== $teamCount || count($state['teams'] ?? []) !== $teamCount) {
            $state['team_count'] = $teamCount;
            $state['teams'] = array_map(fn (array $team) => [...$team, 'member_ids' => [], 'score' => 0, 'correct_answers' => 0, 'answered_questions' => 0], $this->teamDefinitions($teamCount));
        }
        return $state;
    }

    private function ensureCrownState(Quiz $quiz, array $state): array
    {
        if ($quiz->game_mode !== 'steal_crown') return $state;

        $state['crown_holder_id'] ??= null;
        $state['crown_holder_name'] ??= null;
        $state['crown_holder_score'] ??= 0;

        return $state;
    }

    private function teamDefinitions(int $count): array
    {
        $definitions = [
            ['id' => 'red', 'name' => 'Tim Merah', 'color' => '#EF4444'],
            ['id' => 'blue', 'name' => 'Tim Biru', 'color' => '#3B82F6'],
            ['id' => 'gold', 'name' => 'Tim Emas', 'color' => '#F59E0B'],
            ['id' => 'green', 'name' => 'Tim Hijau', 'color' => '#10B981'],
        ];
        return array_slice($definitions, 0, $count);
    }

    private function shuffleTeamState(array $state): array
    {
        foreach ($state['teams'] as &$team) $team['member_ids'] = [];
        unset($team);
        foreach (collect($state['participants'])->shuffle()->values() as $index => $participant) {
            $team = $state['teams'][$index % count($state['teams'])];
            $participant['team_id'] = $team['id'];
            $participant['team_name'] = $team['name'];
            $participant['team_color'] = $team['color'];
            $state['participants'][(string) $participant['user_id']] = $participant;
            $state['teams'][$index % count($state['teams'])]['member_ids'][] = $participant['user_id'];
        }
        return $state;
    }

    private function saveQuizGameState(int $quizId, array $state): void
    {
        Cache::put("quiz:game:{$quizId}", $state, now()->addHours(12));
    }

    private function broadcastGameState(int $quizId, array $state, ?string $message = null): void
    {
        try {
            event(new QuizGameStateUpdated(
                $quizId,
                $state['status'],
                array_values($state['participants'] ?? []),
                $message,
                array_values($state['teams'] ?? []),
                [
                    'holder_id' => $state['crown_holder_id'] ?? null,
                    'holder_name' => $state['crown_holder_name'] ?? null,
                    'score' => (int) ($state['crown_holder_score'] ?? 0),
                ],
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function broadcastQuizStarted(int $quizId, array $state): void
    {
        try {
            event(new QuizStarted($quizId, array_values($state['participants'] ?? [])));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function broadcastQuizEnded(int $quizId): void
    {
        try {
            event(new QuizEnded($quizId, $this->liveLeaderboardData($quizId)));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function broadcastCrownTransfer(int $quizId, ?int $holderId, ?string $holderName, ?int $previousHolderId, string $reason, int $score): void
    {
        try {
            $avatar = null;
            if ($holderId) {
                $avatarPath = User::whereKey($holderId)->value('avatar');
                if ($avatarPath) {
                    $avatar = str_starts_with($avatarPath, 'http') ? $avatarPath : Storage::url($avatarPath);
                }
            }
            event(new CrownTransferred($quizId, $holderId, $holderName, $previousHolderId, $reason, $score, $avatar));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Terima jawaban per soal untuk memperbarui skor sementara live leaderboard.
     */
    public function submitAnswer(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;
        $isAllowed = $user->role === 'admin'
            || $schoolClass->students()->whereKey($user->id)->exists();

        if (! $isAllowed) {
            abort(403, 'Anda tidak terdaftar di kelas kuis ini.');
        }

        $gameState = $this->quizGameState($quiz->id);
        if ($quiz->quiz_mode === 'live' && $gameState['status'] !== 'running') {
            abort(403, 'Game live belum dimulai atau sudah berakhir.');
        }
        if ($quiz->quiz_mode === 'assign' && $quiz->due_at?->isPast()) {
            abort(403, 'Tenggat waktu kuis ini telah berakhir.');
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['nullable', 'integer'],
            'option_ids' => ['nullable', 'array', 'min:1'],
            'option_ids.*' => ['integer'],
            'powerup_used' => ['nullable', 'in:fifty_fifty,freeze_time'],
            'elapsed_seconds' => ['nullable', 'numeric', 'min:0', 'max:600'],
        ]);

        $question = $quiz->questions()->with('options')->findOrFail($validated['question_id']);
        $selectedIds = $question->question_type === 'multiple_choice_multiple'
            ? array_values(array_unique(array_map('intval', $validated['option_ids'] ?? [])))
            : [(int) ($validated['option_id'] ?? 0)];
        $selectedOptions = $question->options->whereIn('id', $selectedIds);
        abort_unless($selectedOptions->count() === count($selectedIds) && count($selectedIds) > 0, 422, 'Pilihan jawaban tidak valid untuk soal ini.');

        $stateKey = "quiz:live:{$quiz->id}:user:{$user->id}";
        $state = Cache::get($stateKey, [
            'answers' => [],
            'score' => 0,
            'current_streak' => 0,
            'reward_index' => 0,
            'powerups' => ['fifty_fifty' => 0, 'freeze_time' => 0],
        ]);
        $state['powerups'] ??= ['fifty_fifty' => 0, 'freeze_time' => 0];
        $questionKey = (string) $question->id;

        $liveGameState = $this->ensureCrownState($quiz, $this->quizGameState($quiz->id));
        $previousCrownId = $liveGameState['crown_holder_id'] ?? null;
        $wasCrownHolder = $quiz->game_mode === 'steal_crown'
            && $previousCrownId !== null
            && (int) $previousCrownId === (int) $user->id;

        // Idempotent: refresh atau klik ganda tidak boleh menggandakan poin.
        if (array_key_exists($questionKey, $state['answers'] ?? [])) {
            return response()->json([
                'current_score' => (int) ($state['score'] ?? 0),
                'rank' => $this->liveRank($quiz->id, $user->id),
                'leaderboard' => $this->liveLeaderboardData($quiz->id),
                'current_streak' => (int) ($state['current_streak'] ?? 0),
                'powerup_stocks' => $state['powerups'],
                'crown_holder_id' => $liveGameState['crown_holder_id'] ?? null,
                'crown_holder_name' => $liveGameState['crown_holder_name'] ?? null,
                'crown_holder_score' => (int) ($liveGameState['crown_holder_score'] ?? 0),
                'crown_multiplier' => 1,
            ]);
        }

        $elapsed = max(0, min(
            (float) ($validated['elapsed_seconds'] ?? ($question->time_limit_seconds ?: $quiz->time_per_question ?: 30)),
            (float) ($question->time_limit_seconds ?: $quiz->time_per_question ?: 30)
        ));
        $limit = max(1, (int) ($question->time_limit_seconds ?: $quiz->time_per_question ?: 30));
        $correctIds = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        sort($selectedIds);
        $isCorrect = $selectedIds === $correctIds;
        if (! empty($validated['powerup_used']) && ($state['powerups'][$validated['powerup_used']] ?? 0) > 0) {
            $state['powerups'][$validated['powerup_used']]--;
        }
        $earned = $isCorrect
            ? (int) round($question->points * (1 + (0.5 * (1 - ($elapsed / $limit)))))
            : 0;
        $crownMultiplier = $wasCrownHolder && $isCorrect ? 2 : 1;
        $earned *= $crownMultiplier;

        $state['answers'][$questionKey] = [
            'option_ids' => $selectedIds,
            'correct' => $isCorrect,
            'elapsed_seconds' => $elapsed,
        ];
        $state['current_streak'] = 0;
        foreach (array_reverse($state['answers']) as $answer) {
            if (! ($answer['correct'] ?? false)) break;
            $state['current_streak']++;
        }
        $powerupReward = null;
        if ($isCorrect && $state['current_streak'] > 0 && $state['current_streak'] % 3 === 0) {
            $rewardType = ((int) ($state['reward_index'] ?? 0) % 2 === 0) ? 'fifty_fifty' : 'freeze_time';
            $state['powerups'][$rewardType]++;
            $state['reward_index'] = ((int) ($state['reward_index'] ?? 0)) + 1;
            $powerupReward = $rewardType;
        }
        $state['score'] = (int) ($state['score'] ?? 0) + $earned;
        Cache::put($stateKey, $state, now()->addHours(2));

        $teamScores = [];
        if ($quiz->game_mode === 'team_battle') {
            $gameState = $this->ensureTeamState($quiz, $this->quizGameState($quiz->id));
            $participant = $gameState['participants'][(string) $user->id] ?? [];
            $teamId = $participant['team_id'] ?? null;
            foreach ($gameState['teams'] as &$team) {
                if ($team['id'] !== $teamId) continue;
                $team['score'] = (int) ($team['score'] ?? 0) + $earned;
                $team['answered_questions'] = (int) ($team['answered_questions'] ?? 0) + 1;
                $team['correct_answers'] = (int) ($team['correct_answers'] ?? 0) + (int) $isCorrect;
            }
            unset($team);
            $this->saveQuizGameState($quiz->id, $gameState);
            $teamScores = array_map(fn (array $team): array => [...$team, 'accuracy' => ($team['answered_questions'] ?? 0) > 0 ? round(($team['correct_answers'] / $team['answered_questions']) * 100, 1) : 0], $gameState['teams']);
            try {
                event(new QuizTeamScoresUpdated($quiz->id, $teamScores));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $boardKey = "quiz:live:{$quiz->id}:leaderboard";
        $board = Cache::get($boardKey, []);
        $board[(string) $user->id] = [
            'user_id' => $user->id,
            'name' => $user->name,
            'current_score' => (int) $state['score'],
            'updated_at' => now()->timestamp,
        ];
        Cache::put($boardKey, $board, now()->addHours(2));

        if ($quiz->game_mode === 'steal_crown') {
            $candidates = array_values($board);
            usort($candidates, function (array $left, array $right): int {
                $scoreComparison = ((int) ($right['current_score'] ?? 0)) <=> ((int) ($left['current_score'] ?? 0));
                return $scoreComparison !== 0
                    ? $scoreComparison
                    : ((int) ($left['updated_at'] ?? 0)) <=> ((int) ($right['updated_at'] ?? 0));
            });

            if (! $isCorrect && $wasCrownHolder) {
                $candidates = array_values(array_filter(
                    $candidates,
                    fn (array $candidate): bool => (int) ($candidate['user_id'] ?? 0) !== (int) $user->id,
                ));
            }

            $newCrown = collect($candidates)->first(fn (array $candidate): bool => (int) ($candidate['current_score'] ?? 0) > 0);
            $newCrownId = $newCrown ? (int) $newCrown['user_id'] : null;
            $newCrownName = $newCrown['name'] ?? null;
            $newCrownScore = (int) ($newCrown['current_score'] ?? 0);
            $oldCrownId = $previousCrownId !== null ? (int) $previousCrownId : null;

            $liveGameState['crown_holder_id'] = $newCrownId;
            $liveGameState['crown_holder_name'] = $newCrownName;
            $liveGameState['crown_holder_score'] = $newCrownScore;
            $this->saveQuizGameState($quiz->id, $liveGameState);

            if ($oldCrownId !== $newCrownId) {
                $reason = ! $isCorrect && $wasCrownHolder
                    ? 'pemegang_salah'
                    : ($oldCrownId !== null && $newCrownId !== null ? 'mahkota_direbut' : 'mahkota_diberikan');
                $this->broadcastCrownTransfer($quiz->id, $newCrownId, $newCrownName, $oldCrownId, $reason, $newCrownScore);
            }
        }

        $answeredKey = "quiz:live:{$quiz->id}:q:{$question->id}:answered";
        $answeredUsers = Cache::get($answeredKey, []);
        $answeredUsers[(string) $user->id] = true;
        Cache::put($answeredKey, $answeredUsers, now()->addHours(2));
        $answeredCount = count($answeredUsers);

        $totalParticipants = max(count($liveGameState['participants'] ?? []), $answeredCount);

        try {
            event(new AnswerSubmitted(
                quizId: $quiz->id,
                questionId: $question->id,
                userId: $user->id,
                studentName: $user->name,
                answeredCount: $answeredCount,
                totalParticipants: $totalParticipants,
                isCorrect: $isCorrect,
                elapsedSeconds: $elapsed,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }

        $leaderboard = $this->liveLeaderboardData($quiz->id);
        $rank = $this->liveRank($quiz->id, $user->id);
        $accuracy = $this->liveAccuracy($quiz->id);

        try {
            event(new ScoreUpdated(
                quizId: $quiz->id,
                leaderboard: $leaderboard,
                teams: $teamScores,
                studentName: $user->name,
                currentScore: (int) $state['score'],
                rank: $rank,
                classAccuracy: $accuracy,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }

        try {
            event(new QuizScoreUpdated($quiz->id, $user->name, (int) $state['score'], $rank, $leaderboard, $accuracy));
        } catch (\Throwable $exception) {
            // Skor tetap tersimpan dan fallback polling tetap berjalan jika Reverb sedang mati.
            report($exception);
        }

        return response()->json([
            'current_score' => (int) $state['score'],
            'rank' => $rank,
            'leaderboard' => $leaderboard,
            'class_accuracy' => $this->liveAccuracy($quiz->id),
            'is_correct' => $isCorrect,
            'current_streak' => (int) $state['current_streak'],
            'powerup_reward' => $powerupReward,
            'powerup_stocks' => $state['powerups'],
            'team_scores' => $teamScores,
            'crown_holder_id' => $liveGameState['crown_holder_id'] ?? null,
            'crown_holder_name' => $liveGameState['crown_holder_name'] ?? null,
            'crown_holder_score' => (int) ($liveGameState['crown_holder_score'] ?? 0),
            'crown_multiplier' => $crownMultiplier,
        ]);
    }

    /**
     * Fallback polling endpoint untuk browser yang belum memuat Laravel Echo.
     */
    public function liveLeaderboard(Request $request, Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;
        $isAllowed = $user->role === 'admin'
            || $user->role === 'guru' && $schoolClass->teacher_id === $user->id
            || $schoolClass->students()->whereKey($user->id)->exists();

        abort_unless($isAllowed, 403, 'Anda tidak memiliki akses ke leaderboard kuis ini.');

        return response()->json([
            'leaderboard' => $this->liveLeaderboardData($quiz->id),
            'class_accuracy' => $this->liveAccuracy($quiz->id),
        ]);
    }

    private function liveAccuracy(int $quizId): float
    {
        $state = $this->quizGameState($quizId);
        $answered = 0;
        $correct = 0;

        foreach (array_keys($state['participants'] ?? []) as $userId) {
            $studentState = Cache::get("quiz:live:{$quizId}:user:{$userId}", []);
            foreach ($studentState['answers'] ?? [] as $answer) {
                $answered++;
                $correct += (int) ($answer['correct'] ?? false);
            }
        }

        return $answered > 0 ? round(($correct / $answered) * 100, 1) : 0.0;
    }

    private function liveLeaderboardData(int $quizId): array
    {
        $board = array_values(Cache::get("quiz:live:{$quizId}:leaderboard", []));
        usort($board, fn (array $a, array $b): int => $b['current_score'] <=> $a['current_score']);

        return array_values(array_map(
            fn (array $item, int $index): array => [...$item, 'rank' => $index + 1],
            array_slice($board, 0, 5),
            array_keys(array_slice($board, 0, 5)),
        ));
    }

    private function liveRank(int $quizId, int $userId): int
    {
        $leaderboard = $this->liveLeaderboardData($quizId);
        $index = collect($leaderboard)->search(fn (array $item): bool => (int) $item['user_id'] === $userId);

        return $index === false ? count($leaderboard) + 1 : $index + 1;
    }

    /**
     * Submit jawaban kuis, hitung skor otomatis, dan simpan attempt.
     */
    public function attempt(Request $request, Quiz $quiz): RedirectResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;

        $isStudent = $schoolClass->students()->whereKey($user->id)->exists() || $user->role === 'admin';
        if (! $isStudent) {
            abort(403, 'Anda tidak terdaftar di kelas kuis ini.');
        }

        if ($user->role !== 'admin' && $quiz->quiz_mode === 'assign' && $quiz->due_at?->isPast()) {
            return back()->withErrors(['quiz' => 'Tenggat waktu kuis ini telah berakhir.']);
        }

        $attemptsUsed = $quiz->attempts()->where('user_id', $user->id)->count();
        if ($user->role !== 'admin' && $quiz->max_attempts !== null && $attemptsUsed >= $quiz->max_attempts) {
            return back()->withErrors([
                'quiz' => "Batas percobaan kuis telah tercapai ({$quiz->max_attempts} kali).",
            ]);
        }

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answer_times' => ['nullable', 'array'],
            'powerups_used' => ['nullable', 'array', 'max:20'],
            'powerups_used.*' => ['string', 'in:fifty_fifty,freeze_time'],
            'time_taken_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
        ]);

        $submittedAnswers = $validated['answers']; // format: [question_id => selected_option_id]

        // Load semua pertanyaan dan opsi lengkap dengan kunci jawaban dari database
        $questions = $quiz->questions()->with('options')->get();

        $totalQuestions = $questions->count();
        $totalCorrect = 0;
        $totalDynamicPoints = 0;
        $answerTimes = collect($validated['answer_times'] ?? []);
        $usesSpeedScoring = $answerTimes->isNotEmpty();
        $maxDynamicPoints = $usesSpeedScoring
            ? ($questions->sum(fn ($question) => $question->points * 1.5) ?: 1)
            : ($questions->sum('points') ?: 1);

        foreach ($questions as $question) {
            $selected = $submittedAnswers[$question->id] ?? $submittedAnswers[(string) $question->id] ?? null;
            $selectedIds = $question->question_type === 'multiple_choice_multiple'
                ? array_values(array_unique(array_map('intval', is_array($selected) ? $selected : [])))
                : [(int) $selected];
            $correctIds = $question->options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            sort($selectedIds);

            if ($selectedIds === $correctIds) {
                $totalCorrect++;
                if ($usesSpeedScoring) {
                    $questionLimit = max(1, (int) ($question->time_limit_seconds ?: $quiz->time_per_question ?: 30));
                    $elapsed = max(0, min($questionLimit, (float) ($answerTimes->get((string) $question->id, $questionLimit))));
                    $speedRatio = 1 - ($elapsed / $questionLimit);
                    $totalDynamicPoints += $question->points * (1 + (0.5 * $speedRatio));
                } else {
                    $totalDynamicPoints += $question->points;
                }
            }
        }

        // Skor akhir dalam skala 0 - 100
        $dynamicScore = (int) round($totalDynamicPoints);
        $finalScore = (int) round(($totalDynamicPoints / $maxDynamicPoints) * 100);

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => $finalScore,
            'dynamic_score' => $dynamicScore,
            'total_correct' => $totalCorrect,
            'total_questions' => $totalQuestions,
            'time_taken_seconds' => $validated['time_taken_seconds'] ?? null,
            'answers' => $submittedAnswers,
            'answer_times' => $validated['answer_times'] ?? [],
            'powerups_used' => array_values(array_unique($validated['powerups_used'] ?? [])),
            'completed_at' => now(),
        ]);

        return back()->with('success', "Kuis selesai! Skor Anda: {$finalScore} / 100");
    }

    /**
     * Hapus kuis (Guru/Admin).
     */
    public function destroy(Request $request, Quiz $quiz): RedirectResponse
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;

        if ($user->role !== 'admin' && ($user->role !== 'guru' || $schoolClass->teacher_id !== $user->id)) {
            abort(403, 'Anda tidak memiliki hak untuk menghapus kuis ini.');
        }

        $classId = $quiz->class_id;
        $quiz->delete();

        return redirect()->route('classes.show', $classId)
            ->with('success', 'Kuis berhasil dihapus.');
    }
}
