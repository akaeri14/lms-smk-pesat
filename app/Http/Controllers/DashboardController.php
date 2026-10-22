<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Quiz;
use App\Models\SchoolClass;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return match ($user->role) {
            'guru' => $this->teacherDashboard($user),
            'siswa' => $this->studentDashboard($user),
            default => $this->adminDashboard(),
        };
    }

    private function teacherDashboard(User $user): Response
    {
        $classIds = SchoolClass::query()->where('teacher_id', $user->id)->pluck('id');

        $classes = SchoolClass::query()
            ->whereIn('id', $classIds)
            ->where('status', 'active')
            ->withCount('students')
            ->withCount('assignments')
            ->latest()
            ->take(6)
            ->get(['id', 'name', 'subject', 'code', 'academic_year', 'status']);

        $recentSubmissions = Submission::query()
            ->whereIn('status', ['submitted', 'late'])
            ->whereNull('score')
            ->whereHas('assignment', fn ($query) => $query->whereIn('class_id', $classIds))
            ->with([
                'student:id,name',
                'assignment:id,class_id,title,deadline',
                'assignment.schoolClass:id,name',
            ])
            ->latest('submitted_at')
            ->take(8)
            ->get();

        $studentCount = User::query()
            ->whereHas('enrolledClasses', fn ($query) => $query->whereIn('classes.id', $classIds))
            ->count();

        return Inertia::render('Dashboard', [
            'role' => 'guru',
            'stats' => [
                ['label' => 'Kelas Diampu', 'value' => $classIds->count(), 'tone' => 'orange', 'icon' => 'class'],
                ['label' => 'Total Siswa', 'value' => $studentCount, 'tone' => 'blue', 'icon' => 'students'],
                ['label' => 'Belum Dinilai', 'value' => Submission::whereIn('status', ['submitted', 'late'])->whereNull('score')->whereHas('assignment', fn ($query) => $query->whereIn('class_id', $classIds))->count(), 'tone' => 'red', 'icon' => 'review'],
            ],
            'classes' => $classes,
            'recentSubmissions' => $recentSubmissions,
        ]);
    }

    private function studentDashboard(User $user): Response
    {
        $classIds = $user->enrolledClasses()->where('classes.status', 'active')->pluck('classes.id');

        $pendingAssignmentsQuery = Assignment::query()
            ->whereIn('class_id', $classIds)
            ->where('deadline', '>=', now())
            ->whereDoesntHave('submissions', fn ($query) => $query->where('student_id', $user->id));

        $pendingAssignmentsCount = (clone $pendingAssignmentsQuery)->count();

        $upcomingAssignments = (clone $pendingAssignmentsQuery)
            ->with('schoolClass:id,name,subject')
            ->orderBy('deadline')
            ->take(6)
            ->get(['id', 'class_id', 'title', 'description', 'deadline', 'max_score']);

        $activeQuizIds = Quiz::query()
            ->whereIn('class_id', $classIds)
            ->where('status', 'published')
            ->where('is_published', true)
            ->whereDoesntHave('attempts', fn ($query) => $query->where('user_id', $user->id))
            ->pluck('id');

        return Inertia::render('Dashboard', [
            'role' => 'siswa',
            'stats' => [
                ['label' => 'Kelas Diikuti', 'value' => $classIds->count(), 'tone' => 'orange', 'icon' => 'class'],
                ['label' => 'Tugas Belum Dikerjakan', 'value' => $pendingAssignmentsCount, 'tone' => 'red', 'icon' => 'assignment'],
                ['label' => 'Kuis Belum Diikuti', 'value' => $activeQuizIds->count(), 'tone' => 'purple', 'icon' => 'quiz'],
            ],
            'upcomingAssignments' => $upcomingAssignments,
        ]);
    }

    private function adminDashboard(): Response
    {
        $recentClasses = SchoolClass::query()
            ->with('teacher:id,name')
            ->withCount('students')
            ->latest()
            ->take(8)
            ->get(['id', 'name', 'subject', 'teacher_id', 'academic_year', 'status', 'created_at']);

        return Inertia::render('Dashboard', [
            'role' => 'admin',
            'stats' => [
                ['label' => 'Total User', 'value' => User::count(), 'tone' => 'orange', 'icon' => 'users'],
                ['label' => 'Total Guru', 'value' => User::where('role', 'guru')->count(), 'tone' => 'blue', 'icon' => 'teacher'],
                ['label' => 'Total Siswa', 'value' => User::where('role', 'siswa')->count(), 'tone' => 'green', 'icon' => 'students'],
                ['label' => 'Total Kelas', 'value' => SchoolClass::count(), 'tone' => 'purple', 'icon' => 'class'],
                ['label' => 'Total Kuis', 'value' => Quiz::count(), 'tone' => 'red', 'icon' => 'quiz'],
            ],
            'recentClasses' => $recentClasses,
        ]);
    }
}
