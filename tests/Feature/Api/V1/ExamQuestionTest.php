<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\ExamQuestion;
use App\Models\Exam;
use App\Models\Question;
use App\Enums\UserTypeEnum;
use App\Enums\QuestionTypeEnum;
use App\Enums\QuestionDifficultyLevelEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

describe('ExamQuestion Management', function () {
    it('can list exam questions', function () {
        ExamQuestion::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/exam-questions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can show an exam question', function () {
        $examQuestion = ExamQuestion::factory()->create();

        $response = $this->getJson("/api/v1/exam-questions/{$examQuestion->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $examQuestion->id,
                ],
            ]);
    });

    it('can create an exam question', function () {
        $this->withoutExceptionHandling();
        $exam = Exam::factory()->create();
        $question = Question::factory()->create();

        $data = [
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'question_number' => 1,
            'content' => 'What is the capital of France?',
            'options' => [
                ['id' => '01JCEXAMPLE1', 'text' => 'Paris', 'media_id' => null],
                ['id' => '01JCEXAMPLE2', 'text' => 'London', 'media_id' => null],
                ['id' => '01JCEXAMPLE3', 'text' => 'Berlin', 'media_id' => null],
                ['id' => '01JCEXAMPLE4', 'text' => 'Madrid', 'media_id' => null],
            ],
            'key_answer' => ['01JCEXAMPLE1'],
            'score_value' => 10,
            'question_type' => QuestionTypeEnum::MULTIPLE_CHOICE->value,
            'difficulty_level' => QuestionDifficultyLevelEnum::Easy->value,
            'hint' => 'Think about the Eiffel Tower',
        ];

        $response = $this->postJson('/api/v1/exam-questions', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('exam_questions', [
            'exam_id' => $exam->id,
            'question_number' => 1,
            'score_value' => 10,
        ]);
    });

    it('can update an exam question', function () {
        $examQuestion = ExamQuestion::factory()->create();
        $data = ['question_number' => 5, 'score_value' => 20];

        $response = $this->putJson("/api/v1/exam-questions/{$examQuestion->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exam_questions', [
            'id' => $examQuestion->id,
            'question_number' => 5,
            'score_value' => 20,
        ]);
    });

    it('can delete an exam question', function () {
        $examQuestion = ExamQuestion::factory()->create();

        $response = $this->deleteJson("/api/v1/exam-questions/{$examQuestion->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('exam_questions', ['id' => $examQuestion->id]);
    });

    it('can bulk delete exam questions', function () {
        $examQuestions = ExamQuestion::factory()->count(3)->create();
        $ids = $examQuestions->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/exam-questions/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($examQuestions as $examQuestion) {
            $this->assertDatabaseMissing('exam_questions', ['id' => $examQuestion->id]);
        }
    });

    it('can bulk update exam questions', function () {
        $examQuestions = ExamQuestion::factory()->count(2)->create();
        $data = [
            'exam_questions' => [
                ['id' => $examQuestions[0]->id, 'question_number' => 10, 'score_value' => 15],
                ['id' => $examQuestions[1]->id, 'question_number' => 20, 'score_value' => 25],
            ],
        ];

        $response = $this->postJson('/api/v1/exam-questions/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('exam_questions', ['id' => $examQuestions[0]->id, 'question_number' => 10, 'score_value' => 15]);
        $this->assertDatabaseHas('exam_questions', ['id' => $examQuestions[1]->id, 'question_number' => 20, 'score_value' => 25]);
    });

    it('returns 404 when exam question not found', function () {
        $response = $this->getJson('/api/v1/exam-questions/01JCNOTFOUND000000000000');

        $response->assertStatus(404);
    });

    it('validates required fields on create', function () {
        $response = $this->postJson('/api/v1/exam-questions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['exam_id', 'question_id', 'question_number', 'content', 'options', 'key_answer', 'score_value', 'question_type', 'difficulty_level']);
    });
});
