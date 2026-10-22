<?php

namespace App\Http\Controllers;

use App\Http\Requests\JoinClassRequest;
use App\Http\Requests\StoreClassRequest;
use App\Http\Requests\UpdateClassRequest;
use App\Models\Assignment;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClassController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SchoolClass::class);

        $classes = SchoolClass::query()
            ->with('teacher:id,name')
            ->withCount('students')
            ->when($request->user()->role === 'guru', fn ($query) => $query->where('teacher_id', $request->user()->id))
            ->when($request->user()->role === 'siswa', fn ($query) => $query->whereHas('students', fn ($query) => $query->whereKey($request->user())))
            ->latest()
            ->get();

        return Inertia::render('Classes/Index', [
            'classes' => $classes,
            'canCreate' => $request->user()->can('create', SchoolClass::class),
            'canJoin' => $request->user()->can('join', SchoolClass::class),
        ]);
    }

    public function store(StoreClassRequest $request): RedirectResponse
    {
        $schoolClass = SchoolClass::query()->create([
            ...$request->validated(),
            'code' => $this->generateClassCode(),
            'teacher_id' => $request->user()->id,
        ]);

        return Redirect::route('classes.show', $schoolClass)->with('success', 'Kelas berhasil dibuat.');
    }

    public function show(Request $request, SchoolClass $schoolClass): Response
    {
        $this->authorize('view', $schoolClass);

        $user = $request->user();
        $canManage = $user->can('update', $schoolClass);

        $schoolClass->load([
            'teacher:id,name,email',
            'students' => function ($query) use ($canManage): void {
                // Relasi belongsToMany menggabungkan users dengan class_user;
                // kualifikasi nama tabel mencegah kolom id menjadi ambigu.
                $columns = ['users.id', 'users.name', 'users.email', 'users.avatar'];
                if ($canManage) {
                    $columns = [...$columns, 'users.profiling_data', 'users.profiling_synced_at'];
                }
                $query->select($columns);
            },
            'assignments' => fn ($query) => $query
                ->with('creator:id,name')
                ->withCount('submissions')
                ->when($canManage, fn ($q) => $q->with([
                    'submissions:id,assignment_id,student_id,submitted_at,status,score',
                ]))
                ->when($user->role === 'siswa', fn ($q) => $q->with([
                    'submissions' => fn ($subQuery) => $subQuery->where('student_id', $user->id),
                ]))
                ->latest('deadline'),
            'quizzes' => fn ($query) => $query
                ->with('creator:id,name')
                ->withCount(['questions', 'attempts'])
                ->when($canManage, fn ($q) => $q->with([
                    'attempts:id,quiz_id,user_id,score,total_correct,total_questions,completed_at',
                ]))
                ->when($user->role === 'siswa', fn ($q) => $q->with([
                    'attempts' => fn ($aq) => $aq->where('user_id', $user->id)->latest('completed_at'),
                ]))
                ->latest(),
        ])->loadCount('students');

        return Inertia::render('Classes/Show', [
            'schoolClass' => $schoolClass,
            'canManage' => $canManage,
            'canCreateAssignment' => $request->user()->can('create', [Assignment::class, $schoolClass]),
            'canCreateQuiz' => $user->role === 'admin' || ($user->role === 'guru' && $schoolClass->teacher_id === $user->id),
            'profilingSummary' => $canManage ? $this->buildProfilingSummary($schoolClass->students) : null,
            'gradebook' => $canManage ? $this->buildGradebook($schoolClass) : null,
        ]);
    }

    private function buildGradebook(SchoolClass $schoolClass): array
    {
        $items = collect();

        foreach ($schoolClass->assignments as $assignment) {
            $items->push([
                'key' => 'assignment-'.$assignment->id,
                'type' => 'assignment',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'due_at' => $assignment->deadline?->toIso8601String(),
                'max_score' => (int) $assignment->max_score,
            ]);
        }

        foreach ($schoolClass->quizzes as $quiz) {
            $items->push([
                'key' => 'quiz-'.$quiz->id,
                'type' => 'quiz',
                'id' => $quiz->id,
                'title' => $quiz->title,
                'due_at' => null,
                'max_score' => 100,
            ]);
        }

        $rows = $schoolClass->students->map(function ($student) use ($schoolClass, $items): array {
            $grades = [];

            foreach ($schoolClass->assignments as $assignment) {
                $submission = $assignment->submissions->firstWhere('student_id', $student->id);
                $isPastDue = $assignment->deadline && $assignment->deadline->isPast();
                $grades['assignment-'.$assignment->id] = $this->gradebookCell(
                    $submission?->score,
                    $submission ? ($submission->score !== null ? 'graded' : 'submitted') : ($isPastDue ? 'missing' : 'pending'),
                );
            }

            foreach ($schoolClass->quizzes as $quiz) {
                $attempt = $quiz->attempts->firstWhere('user_id', $student->id);
                $grades['quiz-'.$quiz->id] = $this->gradebookCell(
                    $attempt?->score,
                    $attempt ? 'graded' : 'pending',
                );
            }

            return [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'email' => $student->email,
                ],
                'grades' => $grades,
            ];
        })->values()->all();

        $averages = [];
        foreach ($items as $item) {
            $scores = collect($rows)
                ->map(fn (array $row) => $row['grades'][$item['key']]['score'] ?? null)
                ->filter(fn ($score) => $score !== null)
                ->values();

            $averages[$item['key']] = $scores->isEmpty()
                ? null
                : round($scores->avg(), 1);
        }

        return [
            'students_count' => count($rows),
            'items' => $items->values()->all(),
            'rows' => $rows,
            'averages' => $averages,
        ];
    }

    private function gradebookCell(?int $score, string $status): array
    {
        return [
            'score' => $score,
            'status' => $status,
        ];
    }

    /** @param \Illuminate\Support\Collection<int, \App\Models\User> $students */
    private function buildProfilingSummary($students): array
    {
        $counts = ['visual' => 0, 'auditori' => 0, 'kinestetik' => 0];
        $profiled = 0;

        foreach ($students as $student) {
            $data = is_array($student->profiling_data) ? $student->profiling_data : [];
            $style = collect([
                Arr::get($data, 'gaya_belajar'),
                Arr::get($data, 'gayaBelajar'),
                Arr::get($data, 'learning_style'),
                Arr::get($data, 'learningStyle'),
                Arr::get($data, 'analysis.gaya_belajar'),
                Arr::get($data, 'hasil.gaya_belajar'),
            ])->filter()->map(fn ($value) => strtolower((string) $value))->first();

            if (! $style) {
                continue;
            }

            $key = str_contains($style, 'visual')
                ? 'visual'
                : (str_contains($style, 'aud') ? 'auditori' : (str_contains($style, 'kin') ? 'kinestetik' : null));

            if ($key) {
                $counts[$key]++;
                $profiled++;
            }
        }

        return [
            'total_students' => $students->count(),
            'profiled_students' => $profiled,
            'distribution' => collect($counts)->map(function ($count, $key) use ($profiled) {
                return [
                    'key' => $key,
                    'label' => ucfirst($key),
                    'count' => $count,
                    'percentage' => $profiled > 0 ? (int) round(($count / $profiled) * 100) : 0,
                ];
            })->values()->all(),
        ];
    }

    public function update(UpdateClassRequest $request, SchoolClass $schoolClass): RedirectResponse
    {
        $schoolClass->update($request->validated());

        return Redirect::route('classes.show', $schoolClass)->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, SchoolClass $schoolClass): RedirectResponse
    {
        $this->authorize('delete', $schoolClass);

        $schoolClass->delete();

        return Redirect::route('classes.index')->with('success', 'Kelas berhasil dihapus.');
    }

    public function join(JoinClassRequest $request): RedirectResponse
    {
        $schoolClass = SchoolClass::query()->where('code', Str::upper($request->validated('code')))->first();

        if ($schoolClass === null || $schoolClass->status !== 'active') {
            return Redirect::back()->withErrors(['code' => 'Kode kelas tidak ditemukan atau kelas sudah diarsipkan.']);
        }

        $schoolClass->students()->syncWithoutDetaching([
            $request->user()->id => ['joined_at' => now()],
        ]);

        return Redirect::route('classes.show', $schoolClass)->with('success', 'Berhasil bergabung ke kelas.');
    }

    private function generateClassCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (SchoolClass::query()->where('code', $code)->exists());

        return $code;
    }
}
