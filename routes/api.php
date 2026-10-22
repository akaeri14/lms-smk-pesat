<?php

use App\Http\Controllers\LmsApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/classes', [LmsApiController::class, 'classes']);
    Route::get('/classes/{schoolClass}/assignments', [LmsApiController::class, 'assignments']);
    Route::get('/assignments/{assignment}/submissions', [LmsApiController::class, 'submissions']);
    Route::get('/classes/{schoolClass}/grades', [LmsApiController::class, 'grades']);
    Route::get('/students', [LmsApiController::class, 'students']);
});
