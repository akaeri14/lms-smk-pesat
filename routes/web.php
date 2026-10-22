<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubmissionController;
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

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::post('classes/join', [ClassController::class, 'join'])->name('classes.join');
    Route::get('classes/{schoolClass}/assignments/create', [AssignmentController::class, 'create'])->name('classes.assignments.create');
    Route::post('classes/{schoolClass}/assignments', [AssignmentController::class, 'store'])->name('classes.assignments.store');
    Route::resource('classes', ClassController::class)->except(['create', 'edit'])->parameters(['classes' => 'schoolClass']);
    Route::resource('assignments', AssignmentController::class)->except(['create', 'store', 'edit']);
    Route::post('/assignments/{assignment}/submissions', [SubmissionController::class, 'store'])->name('assignments.submissions.store');
    Route::match(['post', 'patch'], '/submissions/{submission}/grade', [SubmissionController::class, 'grade'])->name('submissions.grade');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
