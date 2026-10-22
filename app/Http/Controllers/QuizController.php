<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('Quizzes/Create', [
            'schoolClass' => $selectedClass,
            'classes' => $classes,
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
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:2000'],
            'questions.*.points' => ['required', 'integer', 'min:1', 'max:100'],
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
            $hasCorrect = collect($questionData['options'])->contains(fn ($opt) => (bool) $opt['is_correct'] === true);
            if (! $hasCorrect) {
                return back()->withErrors([
                    "questions.{$index}" => "Soal nomor ".($index + 1)." harus memiliki minimal satu kunci jawaban yang benar.",
                ]);
            }
        }

        DB::transaction(function () use ($schoolClass, $validated, $user) {
            $quiz = $schoolClass->quizzes()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'duration_minutes' => $validated['duration_minutes'],
                'is_published' => true,
                'status' => 'published',
                'created_by' => $user->id,
            ]);

            foreach ($validated['questions'] as $qData) {
                $question = $quiz->questions()->create([
                    'question_text' => $qData['question_text'],
                    'points' => $qData['points'] ?? 10,
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
    public function downloadTemplate(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Soal Kuis');

        $headers = [
            'Pertanyaan',
            'Opsi A',
            'Opsi B',
            'Opsi C',
            'Opsi D',
            'Kunci Jawaban (A/B/C/D)',
            'Durasi (detik)',
            'Poin',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $samples = [
            [
                'Tag HTML yang digunakan untuk membuat judul tingkat pertama adalah?',
                '<h1>',
                '<p>',
                '<title>',
                '<header>',
                'A',
                30,
                10,
            ],
            [
                'Protokol jaringan yang digunakan untuk mentransfer data halaman web secara terenkripsi/aman adalah?',
                'HTTP',
                'HTTPS',
                'FTP',
                'SMTP',
                'B',
                30,
                10,
            ],
            [
                'Manakah di bawah ini yang merupakan bahasa pemrograman sisi server (backend)?',
                'HTML',
                'CSS',
                'PHP',
                'XML',
                'C',
                30,
                10,
            ],
            [
                'Perangkat keras komputer yang berfungsi sebagai otak pemroses data utama adalah?',
                'RAM',
                'Hard Disk',
                'Power Supply',
                'CPU / Processor',
                'D',
                30,
                10,
            ],
        ];
        $sheet->fromArray($samples, null, 'A2');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'Template_Soal_Kuis_SMKPesat.xlsx';

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

        // Parse baris soal mulai baris kedua (index 1)
        $parsedQuestions = [];
        $totalDurationSeconds = 0;

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $questionText = trim((string) ($row[0] ?? ''));
            if ($questionText === '') {
                continue;
            }

            $optA = trim((string) ($row[1] ?? ''));
            $optB = trim((string) ($row[2] ?? ''));
            $optC = trim((string) ($row[3] ?? ''));
            $optD = trim((string) ($row[4] ?? ''));
            $correctKey = strtoupper(trim((string) ($row[5] ?? 'A')));
            $durationSec = (int) ($row[6] ?? 30);
            $points = (int) ($row[7] ?? 10);
            if ($points <= 0) {
                $points = 10;
            }

            $totalDurationSeconds += $durationSec > 0 ? $durationSec : 30;

            $options = [];
            if ($optA !== '') {
                $options[] = ['text' => $optA, 'is_correct' => $correctKey === 'A'];
            }
            if ($optB !== '') {
                $options[] = ['text' => $optB, 'is_correct' => $correctKey === 'B'];
            }
            if ($optC !== '') {
                $options[] = ['text' => $optC, 'is_correct' => $correctKey === 'C'];
            }
            if ($optD !== '') {
                $options[] = ['text' => $optD, 'is_correct' => $correctKey === 'D'];
            }

            if (count($options) < 2) {
                continue;
            }

            // Pastikan ada setidaknya 1 opsi jawaban benar
            if (! collect($options)->contains(fn ($o) => $o['is_correct'])) {
                $options[0]['is_correct'] = true;
            }

            $parsedQuestions[] = [
                'question_text' => $questionText,
                'points' => $points,
                'options' => $options,
            ];
        }

        if (empty($parsedQuestions)) {
            return back()->withErrors(['file' => 'Tidak ditemukan baris pertanyaan yang valid di dalam file. Silakan periksa kembali template Excel Anda.']);
        }

        $durationMinutes = ! empty($validated['duration_minutes'])
            ? (int) $validated['duration_minutes']
            : max(1, (int) ceil($totalDurationSeconds / 60));

        DB::transaction(function () use ($schoolClass, $validated, $user, $durationMinutes, $parsedQuestions) {
            $quiz = $schoolClass->quizzes()->create([
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'duration_minutes' => $durationMinutes,
                'is_published' => true,
                'status' => 'published',
                'created_by' => $user->id,
            ]);

            foreach ($parsedQuestions as $qData) {
                $question = $quiz->questions()->create([
                    'question_text' => $qData['question_text'],
                    'points' => $qData['points'],
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
            ->orderByDesc('score')
            ->orderBy('completed_at')
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
            'questions'        => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string', 'max:2000'],
            'questions.*.points'        => ['required', 'integer', 'min:1', 'max:100'],
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
            $hasCorrect = collect($questionData['options'])->contains(fn ($opt) => (bool) $opt['is_correct'] === true);
            if (!$hasCorrect) {
                return back()->withErrors([
                    "questions.{$index}" => 'Soal nomor '.($index + 1).' harus memiliki minimal satu kunci jawaban yang benar.',
                ]);
            }
        }

        DB::transaction(function () use ($quiz, $validated) {
            $quiz->update([
                'title'            => $validated['title'],
                'description'      => $validated['description'] ?? null,
                'duration_minutes' => $validated['duration_minutes'],
            ]);

            // Hapus semua soal dan opsi lama, lalu buat ulang
            $quiz->questions()->each(fn ($q) => $q->options()->delete());
            $quiz->questions()->delete();

            foreach ($validated['questions'] as $qData) {
                $question = $quiz->questions()->create([
                    'question_text' => $qData['question_text'],
                    'points'        => $qData['points'] ?? 10,
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
    public function play(Request $request, Quiz $quiz): Response
    {
        $user = $request->user();
        $schoolClass = $quiz->schoolClass;

        $isTeacher = $user->role === 'admin' || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id);
        $isStudent = $schoolClass->students()->whereKey($user->id)->exists();

        if (! $isTeacher && ! $isStudent) {
            abort(403, 'Anda tidak terdaftar di kelas kuis ini.');
        }

        $quiz->load(['schoolClass:id,name,subject']);

        // Load questions with options.
        // PENTING: Untuk mencegah kecurangan inspect element, sembunyikan kolom is_correct saat kuis dimainkan!
        $questions = $quiz->questions()
            ->with(['options' => fn ($query) => $query->select('id', 'quiz_question_id', 'option_text')])
            ->get();

        // Riwayat attempt siswa jika sudah pernah mengerjakan
        $myAttempt = $quiz->attempts()
            ->where('user_id', $user->id)
            ->latest('completed_at')
            ->first();

        // Leaderboard untuk ditampilkan di akhir pengerjaan
        $leaderboard = $quiz->attempts()
            ->with('user:id,name,avatar')
            ->orderByDesc('score')
            ->orderBy('completed_at')
            ->take(10)
            ->get();

        return Inertia::render('Quizzes/Play', [
            'quiz' => $quiz,
            'questions' => $questions,
            'myAttempt' => $myAttempt,
            'leaderboard' => $leaderboard,
        ]);
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

        $validated = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $submittedAnswers = $validated['answers']; // format: [question_id => selected_option_id]

        // Load semua pertanyaan dan opsi lengkap dengan kunci jawaban dari database
        $questions = $quiz->questions()->with('options')->get();

        $totalQuestions = $questions->count();
        $totalCorrect = 0;
        $totalPointsEarned = 0;
        $maxPossiblePoints = $questions->sum('points') ?: 1;

        foreach ($questions as $question) {
            $selectedOptionId = $submittedAnswers[$question->id] ?? null;
            $correctOption = $question->options->firstWhere('is_correct', true);

            if ($correctOption && (int) $selectedOptionId === (int) $correctOption->id) {
                $totalCorrect++;
                $totalPointsEarned += $question->points;
            }
        }

        // Skor akhir dalam skala 0 - 100
        $finalScore = (int) round(($totalPointsEarned / $maxPossiblePoints) * 100);

        QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'user_id' => $user->id,
            'score' => $finalScore,
            'total_correct' => $totalCorrect,
            'total_questions' => $totalQuestions,
            'answers' => $submittedAnswers,
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
