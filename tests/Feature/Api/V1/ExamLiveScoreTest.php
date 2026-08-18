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
use App\Enums\ExamTimerTypeEnum;
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
            'timer_type' => ExamTimerTypeEnum::Strict->value,
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
                    'exam' => ['id', 'title', 'duration', 'classrooms', 'timer_type'],
                    'sessions' => [
                        '*' => [
                            'student',
                            'status',
                            'start_time',
                            'remaining_time',
                            'score',
                            'extra_time'
                        ]
                    ]
                ]
            ]);

        $studentsData = $response->json('data.sessions');

        $alice = collect($studentsData)->firstWhere('student.id', $student1->id);
        expect($alice['status'])->toBe('idle');

        $bob = collect($studentsData)->firstWhere('student.id', $student2->id);
        expect($bob['status'])->toBe('in_progress')
            ->and($bob['remaining_time'])->toBeGreaterThan(0);

        $charlie = collect($studentsData)->firstWhere('student.id', $student3->id);
        expect($charlie['status'])->toBe('finished');
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
        $this->assertSoftDeleted('exam_sessions', ['id' => $session->id]);
        $this->assertSoftDeleted('exam_results', ['id' => $result->id]);
    });

    it('can add extra time for a student only when the exam uses strict timer', function () {
        // Arrange
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'timer_type' => ExamTimerTypeEnum::Strict->value,
        ]);
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);

        $session = ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'is_finished' => false,
            'start_time' => now(),
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

    it('rejects extra time for exams configured with a flexible timer', function () {
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'timer_type' => ExamTimerTypeEnum::Flexible->value,
        ]);
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);

        ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'is_finished' => false,
            'extra_time' => 0,
        ]);

        $response = $this->postJson(route('api.v1.exams.add-time', $exam), [
            'user_id' => $student->id,
            'minutes' => 15,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Extra time is only available for exams with a strict timer.');
    });

    it('does not expose countdown data for exams configured with a flexible timer', function () {
        $academicYear = AcademicYear::factory()->create();
        $classroom = Classroom::factory()->create(['academic_year_id' => $academicYear->id]);
        $subject = Subject::factory()->create(['classroom_id' => $classroom->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'timer_type' => ExamTimerTypeEnum::Flexible->value,
            'duration' => 60,
        ]);
        $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT, 'name' => 'Dana']);

        $classroom->students()->attach($student->id, ['academic_year_id' => $academicYear->id]);

        ExamSession::factory()->create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'is_finished' => false,
            'start_time' => now()->subMinutes(10),
            'extra_time' => 0,
        ]);

        $response = $this->getJson(route('api.v1.exams.live-score', $exam));

        $response->assertOk();
        $session = collect($response->json('data.sessions'))->firstWhere('student.id', $student->id);

        expect($response->json('data.exam.timer_type'))->toBe(ExamTimerTypeEnum::Flexible->value)
            ->and($session['remaining_time'])->toBeNull();
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
