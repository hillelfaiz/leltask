<?php

use App\Http\Controllers\Api\ApiAuthController;
use App\Http\Controllers\Api\ApiDataController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth)
Route::post('/login', [ApiAuthController::class, 'login']);
Route::post('/register', [ApiAuthController::class, 'register']);

// Protected routes (auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [ApiAuthController::class, 'logout']);
    Route::get('/user', [ApiAuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard', [ApiDataController::class, 'dashboard']);

    // Tasks
    Route::post('/tasks', [ApiDataController::class, 'storeTask']);
    Route::put('/tasks/{task}', [ApiDataController::class, 'updateTask']);
    Route::delete('/tasks/{task}', [ApiDataController::class, 'destroyTask']);

    // Notes
    Route::post('/notes', [ApiDataController::class, 'storeNote']);
    Route::put('/notes/{note}', [ApiDataController::class, 'updateNote']);
    Route::delete('/notes/{note}', [ApiDataController::class, 'destroyNote']);
    Route::post('/notes/ai-format', [ApiDataController::class, 'formatNoteWithAI']);

    // Courses
    Route::post('/courses', [ApiDataController::class, 'storeCourse']);
    Route::put('/courses/{course}', [ApiDataController::class, 'updateCourse']);
    Route::delete('/courses/{course}', [ApiDataController::class, 'destroyCourse']);

    // Semester
    Route::put('/semesters/active', [ApiDataController::class, 'updateActiveSemester']);
});
