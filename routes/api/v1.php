<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\QuestionController;
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

    // Students
    Route::prefix('students')->group(function () {
        Route::get('available', [StudentController::class, 'available'])->name('api.v1.students.available');
        Route::get('trashed', [StudentController::class, 'trashed'])->name('api.v1.students.trashed');
        Route::post('{student}/restore', [StudentController::class, 'restore'])->name('api.v1.students.restore');
        Route::delete('{student}/force-delete', [StudentController::class, 'forceDelete'])->name('api.v1.students.force-delete');
        Route::post('bulk-delete', [StudentController::class, 'bulkDelete'])->name('api.v1.students.bulk-delete');
        Route::post('bulk-update', [StudentController::class, 'bulkUpdate'])->name('api.v1.students.bulk-update');
    });
    Route::apiResource('students', StudentController::class)->names('api.v1.students');

    // Subjects
    Route::prefix('subjects')->group(function () {
        Route::get('trashed', [SubjectController::class, 'trashed'])->name('api.v1.subjects.trashed');
        Route::post('{subject}/restore', [SubjectController::class, 'restore'])->name('api.v1.subjects.restore');
        Route::delete('{subject}/force-delete', [SubjectController::class, 'forceDelete'])->name('api.v1.subjects.force-delete');
        Route::post('bulk-delete', [SubjectController::class, 'bulkDelete'])->name('api.v1.subjects.bulk-delete');
        Route::post('bulk-update', [SubjectController::class, 'bulkUpdate'])->name('api.v1.subjects.bulk-update');
    });
    Route::apiResource('subjects', SubjectController::class)->names('api.v1.subjects');

    // Academic Years
    Route::prefix('academic-years')->group(function () {
        Route::get('trashed', [AcademicYearController::class, 'trashed'])->name('api.v1.academic-years.trashed');
        Route::post('{academicYear}/restore', [AcademicYearController::class, 'restore'])->name('api.v1.academic-years.restore');
        Route::delete('{academicYear}/force-delete', [AcademicYearController::class, 'forceDelete'])->name('api.v1.academic-years.force-delete');
        Route::post('bulk-delete', [AcademicYearController::class, 'bulkDelete'])->name('api.v1.academic-years.bulk-delete');
        Route::post('bulk-update', [AcademicYearController::class, 'bulkUpdate'])->name('api.v1.academic-years.bulk-update');
    });
    Route::apiResource('academic-years', AcademicYearController::class)->names('api.v1.academic-years');

    // Classrooms
    Route::prefix('classrooms')->group(function () {
        Route::get('trashed', [ClassroomController::class, 'trashed'])->name('api.v1.classrooms.trashed');
        Route::post('{classroom}/restore', [ClassroomController::class, 'restore'])->name('api.v1.classrooms.restore');
        Route::delete('{classroom}/force-delete', [ClassroomController::class, 'forceDelete'])->name('api.v1.classrooms.force-delete');
        Route::post('bulk-delete', [ClassroomController::class, 'bulkDelete'])->name('api.v1.classrooms.bulk-delete');
        Route::post('bulk-update', [ClassroomController::class, 'bulkUpdate'])->name('api.v1.classrooms.bulk-update');
        Route::post('{classroom}/sync', [ClassroomController::class, 'syncStudents'])->name('api.v1.classrooms.sync');
    });
    Route::apiResource('classrooms', ClassroomController::class)->names('api.v1.classrooms');

    // Questions
    Route::prefix('questions')->group(function () {
        Route::get('trashed', [QuestionController::class, 'trashed'])->name('api.v1.questions.trashed');
        Route::post('{question}/restore', [QuestionController::class, 'restore'])->name('api.v1.questions.restore');
        Route::delete('{question}/force-delete', [QuestionController::class, 'forceDelete'])->name('api.v1.questions.force-delete');
        Route::post('bulk-delete', [QuestionController::class, 'bulkDelete'])->name('api.v1.questions.bulk-delete');
        Route::post('bulk-update', [QuestionController::class, 'bulkUpdate'])->name('api.v1.questions.bulk-update');

        // Media handling
        Route::post('{question}/media', [QuestionController::class, 'uploadMedia'])->name('api.v1.questions.media.upload');
        Route::post('{question}/media/{media}', [QuestionController::class, 'replaceMedia'])->name('api.v1.questions.media.replace');
        Route::delete('{question}/media/{media}', [QuestionController::class, 'deleteMedia'])->name('api.v1.questions.media.delete');
    });
    Route::apiResource('questions', QuestionController::class)->names('api.v1.questions');

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
