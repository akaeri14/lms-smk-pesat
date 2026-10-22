<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LmsApiController extends Controller
{
    public function classes(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $classes = SchoolClass::query()
            ->with([
                'teacher:id,name,email',
                'students:id,name,email',
            ])
            ->withCount('students')
            ->latest()
            ->get();

        return response()->json([
            'data' => $classes->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'subject' => $class->subject,
                'academic_year' => $class->academic_year,
                'status' => $class->status,
                'teacher' => $class->teacher?->only(['id', 'name', 'email']),
                'students_count' => $class->students_count,
                'students' => $class->students->map(fn (User $student) => $student->only(['id', 'name', 'email']))->values(),
            ])->values(),
        ]);
    }

    public function assignments(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->authorizeRead($request);

        $assignments = $schoolClass->assignments()
            ->withCount('submissions')
            ->with('creator:id,name')
            ->orderBy('deadline')
            ->get();

        return response()->json([
            'data' => $assignments->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'deadline' => $assignment->deadline?->toIso8601String(),
                'max_score' => $assignment->max_score,
                'submissions_count' => $assignment->submissions_count,
                'creator' => $assignment->creator?->only(['id', 'name']),
            ])->values(),
        ]);
    }

    public function submissions(Request $request, Assignment $assignment): JsonResponse
    {
        $this->authorizeRead($request);

        $assignment->load('submissions.student:id,name,email');

        return response()->json([
            'data' => [
                'assignment' => [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'deadline' => $assignment->deadline?->toIso8601String(),
                    'max_score' => $assignment->max_score,
                ],
                'submissions' => $assignment->submissions->map(fn ($submission) => [
                    'id' => $submission->id,
                    'student' => $submission->student?->only(['id', 'name', 'email']),
                    'status' => $submission->status,
                    'score' => $submission->score,
                    'submitted_at' => $submission->submitted_at?->toIso8601String(),
                    'graded_at' => $submission->graded_at?->toIso8601String(),
                    'teacher_feedback' => $submission->teacher_feedback,
                ])->values(),
            ],
        ]);
    }

    public function grades(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->authorizeRead($request);

        $schoolClass->load([
            'students:id,name,email',
            'assignments.submissions:id,assignment_id,student_id,status,score,submitted_at',
            'quizzes.attempts:id,quiz_id,user_id,score,completed_at',
        ]);

        $items = $schoolClass->assignments->map(fn ($assignment) => [
            'key' => 'assignment-'.$assignment->id,
            'type' => 'assignment',
            'id' => $assignment->id,
            'title' => $assignment->title,
            'due_at' => $assignment->deadline?->toIso8601String(),
            'max_score' => (int) $assignment->max_score,
        ])->concat($schoolClass->quizzes->map(fn ($quiz) => [
            'key' => 'quiz-'.$quiz->id,
            'type' => 'quiz',
            'id' => $quiz->id,
            'title' => $quiz->title,
            'due_at' => null,
            'max_score' => 100,
        ]))->values();

        $rows = $schoolClass->students->map(function ($student) use ($schoolClass): array {
            $grades = [];

            foreach ($schoolClass->assignments as $assignment) {
                $submission = $assignment->submissions->firstWhere('student_id', $student->id);
                $missing = ! $submission && $assignment->deadline?->isPast();
                $grades['assignment-'.$assignment->id] = [
                    'score' => $submission?->score,
                    'status' => $submission ? ($submission->score === null ? 'submitted' : 'graded') : ($missing ? 'missing' : 'pending'),
                ];
            }

            foreach ($schoolClass->quizzes as $quiz) {
                $attempt = $quiz->attempts->firstWhere('user_id', $student->id);
                $grades['quiz-'.$quiz->id] = [
                    'score' => $attempt?->score,
                    'status' => $attempt ? 'graded' : 'pending',
                ];
            }

            return [
                'student' => $student->only(['id', 'name', 'email']),
                'grades' => $grades,
            ];
        })->values();

        return response()->json([
            'data' => [
                'class' => $schoolClass->only(['id', 'code', 'name', 'subject']),
                'items' => $items,
                'rows' => $rows,
            ],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $this->authorizeRead($request);

        $students = User::query()
            ->where('role', 'siswa')
            ->latest('id')
            ->get(['id', 'name', 'email', 'avatar', 'profiling_external_id', 'profiling_data', 'profiling_synced_at']);

        return response()->json([
            'data' => $students->map(fn (User $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'avatar' => $student->avatar,
                'profiling_external_id' => $student->profiling_external_id,
                'profiling_data' => $student->profiling_data,
                'profiling_synced_at' => $student->profiling_synced_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    private function authorizeRead(Request $request): void
    {
        abort_unless(
            $request->user()?->tokenCan('api:read') || $request->user()?->tokenCan('*'),
            403,
            'Token tidak memiliki ability api:read.',
        );
    }
}
