<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\AdminQuizSettingsController;
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
    Route::get('quizzes/{quiz}/results', [QuizController::class, 'results'])->name('quizzes.results');
    Route::get('quizzes/{quiz}/host', [QuizController::class, 'host'])->name('quizzes.host');
    Route::post('quizzes/{quiz}/join', [QuizController::class, 'joinGame'])->name('quizzes.join');
    Route::get('quizzes/{quiz}/game-state', [QuizController::class, 'gameState'])->name('quizzes.game-state');
    Route::post('quizzes/{quiz}/start', [QuizController::class, 'startGame'])->name('quizzes.start');
    Route::post('quizzes/{quiz}/shuffle-teams', [QuizController::class, 'shuffleTeams'])->name('quizzes.shuffle-teams');
    Route::post('quizzes/{quiz}/pause', [QuizController::class, 'pauseGame'])->name('quizzes.pause');
    Route::post('quizzes/{quiz}/end', [QuizController::class, 'endGame'])->name('quizzes.end');
    Route::post('quizzes/{quiz}/answers', [QuizController::class, 'submitAnswer'])->name('quizzes.answers.store');
    Route::get('quizzes/{quiz}/live-leaderboard', [QuizController::class, 'liveLeaderboard'])->name('quizzes.live-leaderboard');
    Route::post('quizzes/{quiz}/attempts', [QuizController::class, 'attempt'])->name('quizzes.attempts.store');

    // Admin: Manajemen User
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/sync-profiling', [UserController::class, 'syncProfiling'])->name('admin.users.sync-profiling');
    Route::get('/admin/quiz-settings', [AdminQuizSettingsController::class, 'index'])->middleware('role:admin')->name('admin.quiz-settings.index');
    Route::post('/admin/quiz-settings/upload', [AdminQuizSettingsController::class, 'store'])->middleware('role:admin')->name('admin.quiz-settings.upload');
    Route::post('/admin/quiz-settings/tracks', [AdminQuizSettingsController::class, 'store'])->middleware('role:admin')->name('admin.quiz-settings.tracks.store');
    Route::patch('/admin/quiz-settings/tracks/{track}', [AdminQuizSettingsController::class, 'update'])->middleware('role:admin')->name('admin.quiz-settings.tracks.update');
    Route::delete('/admin/quiz-settings/tracks/{track}', [AdminQuizSettingsController::class, 'destroy'])->middleware('role:admin')->name('admin.quiz-settings.tracks.destroy');

    // Admin & developer integration documentation and token management.
    Route::get('/admin/api-docs', [ApiDocsController::class, 'index'])->middleware('role:admin')->name('admin.api-docs.index');
    Route::post('/admin/api-docs/tokens', [ApiDocsController::class, 'createToken'])->middleware('role:admin')->name('admin.api-docs.tokens.store');
    Route::delete('/admin/api-docs/tokens/api/{token}', [ApiDocsController::class, 'destroyApiToken'])->middleware('role:admin')->name('admin.api-docs.tokens.api.destroy');
    Route::delete('/admin/api-docs/tokens/sso/{token}', [ApiDocsController::class, 'destroySsoToken'])->middleware('role:admin')->name('admin.api-docs.tokens.sso.destroy');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/sso/authorize', [ApiDocsController::class, 'ssoAuthorize'])
    ->middleware('throttle:10,1')
    ->name('sso.authorize');

require __DIR__.'/auth.php';
