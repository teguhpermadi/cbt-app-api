<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Routes for API version 1.
|
|--------------------------------------------------------------------------
*/

// Public routes with auth rate limiter (5/min - brute force protection)
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('api.v1.register');
    Route::post('login', [AuthController::class, 'login'])->name('api.v1.login');
});

// Protected routes with authenticated rate limiter (120/min)
Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
    Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');

    // Teachers
    Route::prefix('teachers')->group(function () {
        Route::get('trashed', [TeacherController::class, 'trashed'])->name('api.v1.teachers.trashed');
        Route::post('{teacher}/restore', [TeacherController::class, 'restore'])->name('api.v1.teachers.restore');
        Route::delete('{teacher}/force-delete', [TeacherController::class, 'forceDelete'])->name('api.v1.teachers.force-delete');
        Route::post('bulk-delete', [TeacherController::class, 'bulkDelete'])->name('api.v1.teachers.bulk-delete');
        Route::post('bulk-update', [TeacherController::class, 'bulkUpdate'])->name('api.v1.teachers.bulk-update');
    });
    Route::apiResource('teachers', TeacherController::class)->names('api.v1.teachers');

    // Email verification
    Route::post('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Password reset routes (public with rate limiting)
Route::middleware('throttle:6,1')->group(function (): void {
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->name('password.reset');
});
