<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AcademicYearController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassroomController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherController;
use App\Http\Controllers\Api\V1\QuestionController;
use App\Http\Controllers\Api\V1\OptionController;
use App\Http\Controllers\Api\V1\QuestionBankController;
use App\Http\Controllers\Api\V1\ReadingMaterialController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\ExamQuestionController;
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

        // Student Exams
        Route::prefix('exams')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Student\ExamController::class, 'index'])->name('api.v1.student.exams.index');
            Route::get('{exam}', [\App\Http\Controllers\Api\V1\Student\ExamController::class, 'show'])->name('api.v1.student.exams.show');
            Route::post('{exam}/start', [\App\Http\Controllers\Api\V1\Student\ExamController::class, 'start'])->name('api.v1.student.exams.start');
            Route::get('{exam}/take', [\App\Http\Controllers\Api\V1\Student\ExamController::class, 'take'])->name('api.v1.student.exams.take');
            Route::post('{exam}/answer', [\App\Http\Controllers\Api\V1\Student\ExamController::class, 'saveAnswer'])->name('api.v1.student.exams.answer');
            Route::post('{exam}/finish', [\App\Http\Controllers\Api\V1\Student\ExamController::class, 'finish'])->name('api.v1.student.exams.finish');
        });
    });
    Route::apiResource('students', StudentController::class)->names('api.v1.students');

    // Subjects
    Route::prefix('subjects')->group(function () {
        Route::get('trashed', [SubjectController::class, 'trashed'])->name('api.v1.subjects.trashed');
        Route::post('{subject}/restore', [SubjectController::class, 'restore'])->name('api.v1.subjects.restore');
        Route::delete('{subject}/force-delete', [SubjectController::class, 'forceDelete'])->name('api.v1.subjects.force-delete');
        Route::post('bulk-delete', [SubjectController::class, 'bulkDelete'])->name('api.v1.subjects.bulk-delete');
        Route::post('bulk-update', [SubjectController::class, 'bulkUpdate'])->name('api.v1.subjects.bulk-update');
        Route::get('mine', [SubjectController::class, 'mine'])->name('api.v1.subjects.mine');
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
        Route::get('mine', [ClassroomController::class, 'mine'])->name('api.v1.classrooms.mine');
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

    // Options
    Route::prefix('options')->group(function () {
        // Media handling
        Route::post('{option}/media', [OptionController::class, 'uploadMedia'])->name('api.v1.options.media.upload');
        Route::post('{option}/media/{media}', [OptionController::class, 'replaceMedia'])->name('api.v1.options.media.replace');
        Route::delete('{option}/media/{media}', [OptionController::class, 'deleteMedia'])->name('api.v1.options.media.delete');
    });
    Route::apiResource('options', OptionController::class)->names('api.v1.options');

    // Reading Materials
    Route::prefix('reading-materials')->group(function () {
        Route::get('trashed', [ReadingMaterialController::class, 'trashed'])->name('api.v1.reading-materials.trashed');
        Route::post('{readingMaterial}/restore', [ReadingMaterialController::class, 'restore'])->name('api.v1.reading-materials.restore');
        Route::delete('{readingMaterial}/force-delete', [ReadingMaterialController::class, 'forceDelete'])->name('api.v1.reading-materials.force-delete');
        Route::post('bulk-delete', [ReadingMaterialController::class, 'bulkDelete'])->name('api.v1.reading-materials.bulk-delete');

        // Media handling
        Route::post('{readingMaterial}/media', [ReadingMaterialController::class, 'uploadMedia'])->name('api.v1.reading-materials.media.upload');
        Route::post('{readingMaterial}/media/{media}', [ReadingMaterialController::class, 'replaceMedia'])->name('api.v1.reading-materials.media.replace');
        Route::delete('{readingMaterial}/media/{media}', [ReadingMaterialController::class, 'deleteMedia'])->name('api.v1.reading-materials.media.delete');
    });
    Route::apiResource('reading-materials', ReadingMaterialController::class)->names('api.v1.reading-materials');

    // Question Banks
    Route::prefix('question-banks')->group(function () {
        Route::get('trashed', [QuestionBankController::class, 'trashed'])->name('api.v1.question-banks.trashed');
        Route::post('{questionBank}/restore', [QuestionBankController::class, 'restore'])->name('api.v1.question-banks.restore');
        Route::delete('{questionBank}/force-delete', [QuestionBankController::class, 'forceDelete'])->name('api.v1.question-banks.force-delete');
    });
    Route::apiResource('question-banks', QuestionBankController::class)->names('api.v1.question-banks');

    // Exams
    Route::prefix('exams')->group(function () {
        Route::get('trashed', [ExamController::class, 'trashed'])->name('api.v1.exams.trashed');
        Route::post('{exam}/restore', [ExamController::class, 'restore'])->name('api.v1.exams.restore');
        Route::delete('{exam}/force-delete', [ExamController::class, 'forceDelete'])->name('api.v1.exams.force-delete');
        Route::post('bulk-delete', [ExamController::class, 'bulkDelete'])->name('api.v1.exams.bulk-delete');
        Route::post('bulk-update', [ExamController::class, 'bulkUpdate'])->name('api.v1.exams.bulk-update');
    });
    Route::apiResource('exams', ExamController::class)->names('api.v1.exams');

    // Exam Questions
    Route::prefix('exam-questions')->group(function () {
        Route::post('bulk-delete', [ExamQuestionController::class, 'bulkDelete'])->name('api.v1.exam-questions.bulk-delete');
        Route::post('bulk-update', [ExamQuestionController::class, 'bulkUpdate'])->name('api.v1.exam-questions.bulk-update');
    });
    Route::apiResource('exam-questions', ExamQuestionController::class)->names('api.v1.exam-questions');

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
