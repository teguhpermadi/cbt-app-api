<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ExamResult;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\AcademicYear;
use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->teacher = User::factory()->create([
        'user_type' => UserTypeEnum::TEACHER,
    ]);
    $this->actingAs($this->teacher, 'sanctum');
});

describe('Exam Live Score & Monitoring', function () {
    it('can retrieve live score data', function () {
        // Arrange
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create([
            'academic_year_id' => $academicYear->id,
            'user_id' => $this->teacher->id,
        ]);
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'academic_year_id' => $academicYear->id,
        ]);
        $exam = Exam::factory()->create([
            'user_id' => $this->teacher->id,
            'subject_id' => $subject->id,
            'academic_year_id' => $academicYear->id,
            'duration' => 60,
        ]);

        $student1 = User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Alice']); // Not started
        $student2 = User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Bob']); // Doing
        $student3 = User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Charlie']); // Done

        // Attach students to classroom
        $classroom->students()->attach([
            $student1->id => ['academic_year_id' => $academicYear->id],
            $student2->id => ['academic_year_id' => $academicYear->id],
            $student3->id => ['academic_year_id' => $academicYear->id],
        ]);

        // Create sessions
        // Student 2: Doing
        ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student2->id,
            'is_finished' => false,
            'start_time' => now()->subMinutes(10),
            'extra_time' => 0,
        ]);

        // Student 3: Done
        ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student3->id,
            'is_finished' => true,
            'total_score' => 85,
            'start_time' => now()->subMinutes(50),
            'finish_time' => now()->subMinutes(10),
        ]);

        // Act
        $response = $this->getJson(route('api.v1.exams.live-score', $exam));

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'exam' => ['id', 'title', 'duration', 'classroom'],
                    'students' => [
                        '*' => [
                            'student_id',
                            'name',
                            'status',
                            'start_time',
                            'remaining_time',
                            'current_score',
                            'extra_time'
                        ]
                    ]
                ]
            ]);

        $studentsData = $response->json('data.students');

        $alice = collect($studentsData)->firstWhere('student_id', $student1->id);
        expect($alice['status'])->toBe('not_started');

        $bob = collect($studentsData)->firstWhere('student_id', $student2->id);
        expect($bob['status'])->toBe('doing')
            ->and($bob['remaining_time'])->toBeGreaterThan(0);

        $charlie = collect($studentsData)->firstWhere('student_id', $student3->id);
        expect($charlie['status'])->toBe('done')
            ->and($charlie['current_score'])->toBe(85);
    });

    it('can reset exam for a student', function () {
        // Arrange
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        $exam = Exam::factory()->create(['subject_id' => $subject->id]);
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);

        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        $result = ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
        ]);

        // Act
        $response = $this->postJson(route('api.v1.exams.reset', $exam), [
            'user_id' => $student->id,
        ]);

        // Assert
        $response->assertOk();
        $this->assertDatabaseMissing('exam_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('exam_results', ['id' => $result->id]);
    });

    it('can add extra time for a student', function () {
        // Arrange
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        $exam = Exam::factory()->create(['subject_id' => $subject->id]);
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);

        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'is_finished' => false,
            'extra_time' => 0,
        ]);

        // Act
        $response = $this->postJson(route('api.v1.exams.add-time', $exam), [
            'user_id' => $student->id,
            'minutes' => 15,
        ]);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'extra_time' => 15,
        ]);
    });

    it('can force finish exam for a student', function () {
        // Arrange
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        $exam = Exam::factory()->create(['subject_id' => $subject->id]);
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);

        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'is_finished' => false,
        ]);

        // Act
        $response = $this->postJson(route('api.v1.exams.force-finish', $exam), [
            'user_id' => $student->id,
        ]);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('exam_sessions', [
            'id' => $session->id,
            'is_finished' => true,
        ]);
    });
});
