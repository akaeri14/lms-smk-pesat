<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('classes/join', [ClassController::class, 'join'])->name('classes.join');
    Route::get('classes/{schoolClass}/assignments/create', [AssignmentController::class, 'create'])->name('classes.assignments.create');
    Route::post('classes/{schoolClass}/assignments', [AssignmentController::class, 'store'])->name('classes.assignments.store');
    Route::resource('classes', ClassController::class)->except(['create', 'edit'])->parameters(['classes' => 'schoolClass']);

    // Assignments — pakai resource penuh (termasuk edit, update, destroy)
    Route::resource('assignments', AssignmentController::class)->except(['create', 'store']);
    Route::post('/assignments/{assignment}/submissions', [SubmissionController::class, 'store'])->name('assignments.submissions.store');
    Route::match(['post', 'patch'], '/submissions/{submission}/grade', [SubmissionController::class, 'grade'])->name('submissions.grade');

    // Quizzes
    Route::get('/quizzes/template-excel', [QuizController::class, 'downloadTemplate'])->name('quizzes.template-excel');
    Route::post('/quizzes/import', [QuizController::class, 'import'])->name('quizzes.import');
    Route::get('/quizzes/create', [QuizController::class, 'create'])->name('quizzes.create');
    Route::post('/quizzes', [QuizController::class, 'store'])->name('quizzes.store');
    Route::get('classes/{schoolClass}/quizzes/create', [QuizController::class, 'create'])->name('classes.quizzes.create');
    Route::post('classes/{schoolClass}/quizzes', [QuizController::class, 'store'])->name('classes.quizzes.store');
    Route::resource('quizzes', QuizController::class)->except(['create', 'store']);
    Route::get('quizzes/{quiz}/play', [QuizController::class, 'play'])->name('quizzes.play');
    Route::post('quizzes/{quiz}/attempts', [QuizController::class, 'attempt'])->name('quizzes.attempts.store');

    // Admin: Manajemen User
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
