<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Exam;
use App\Models\AcademicYear;
use App\Models\Subject;
use App\Models\Classroom;
use App\Models\QuestionBank;
use App\Enums\UserTypeEnum;
use App\Enums\ExamTypeEnum;
use App\Enums\ExamTimerTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

describe('Exam Management', function () {
    it('can list exams', function () {
        Exam::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/exams');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can show an exam', function () {
        $exam = Exam::factory()->create();

        $response = $this->getJson("/api/v1/exams/{$exam->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $exam->id,
                ],
            ]);
    });

    it('can create an exam', function () {
        $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
        $academicYear = AcademicYear::factory()->create();
        $subject = Subject::factory()->create();
        $questionBank = QuestionBank::factory()->create();

        $data = [
            'academic_year_id' => $academicYear->id,
            'subject_id' => $subject->id,
            'classroom_ids' => [Classroom::factory()->create()->id],
            'user_id' => $teacher->id,
            'question_bank_id' => $questionBank->id,
            'title' => 'Mathematics Final Exam',
            'type' => ExamTypeEnum::Final->value,
            'duration' => 120,
            'token' => 'EXAM2024',
            'is_token_visible' => true,
            'is_published' => false,
            'is_randomized_question' => true,
            'is_randomized_answer' => true,
            'is_show_result' => true,
            'is_visible_hint' => false,
            'max_attempts' => 2,
            'timer_type' => ExamTimerTypeEnum::Strict->value,
            'passing_score' => 75,
            'start_time' => now()->addDay()->toIso8601String(),
            'end_time' => now()->addDays(2)->toIso8601String(),
        ];

        $response = $this->postJson('/api/v1/exams', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exams', [
            'title' => 'Mathematics Final Exam',
            'type' => ExamTypeEnum::Final->value,
        ]);
    });

    it('can update an exam', function () {
        $exam = Exam::factory()->create();
        $classroom = Classroom::factory()->create();
        $data = [
            'title' => 'Updated Exam Title',
            'classroom_ids' => [$classroom->id]
        ];

        $response = $this->putJson("/api/v1/exams/{$exam->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exams', [
            'id' => $exam->id,
            'title' => 'Updated Exam Title',
        ]);
    });

    it('can soft-delete an exam', function () {
        $exam = Exam::factory()->create();

        $response = $this->deleteJson("/api/v1/exams/{$exam->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($exam);
    });

    it('can list trashed exams', function () {
        $exam = Exam::factory()->create();
        $exam->delete();

        $response = $this->getJson('/api/v1/exams/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore an exam', function () {
        $exam = Exam::factory()->create();
        $exam->delete();

        $response = $this->postJson("/api/v1/exams/{$exam->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($exam);
    });

    it('can force delete an exam', function () {
        $exam = Exam::factory()->create();
        $exam->delete();

        $response = $this->deleteJson("/api/v1/exams/{$exam->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
    });

    it('can bulk delete exams', function () {
        $exams = Exam::factory()->count(3)->create();
        $ids = $exams->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/exams/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($exams as $exam) {
            $this->assertSoftDeleted($exam);
        }
    });

    it('can bulk update exams', function () {
        $exams = Exam::factory()->count(2)->create();
        $data = [
            'exams' => [
                ['id' => $exams[0]->id, 'title' => 'Bulk Update 1'],
                ['id' => $exams[1]->id, 'title' => 'Bulk Update 2'],
            ],
        ];

        $response = $this->postJson('/api/v1/exams/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exams', ['id' => $exams[0]->id, 'title' => 'Bulk Update 1']);
        $this->assertDatabaseHas('exams', ['id' => $exams[1]->id, 'title' => 'Bulk Update 2']);
    });
});
