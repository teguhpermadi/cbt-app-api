<?php

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

use App\Enums\UserTypeEnum;

test('teacher can fetch exam session details for correction', function () {
    $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
    $exam = Exam::factory()->create();
    $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
    $session = ExamSession::factory()->create([
        'exam_id' => $exam->id,
        'user_id' => $student->id,
    ]);
    $question = ExamQuestion::factory()->create(['exam_id' => $exam->id]);
    ExamResultDetail::factory()->create([
        'exam_session_id' => $session->id,
        'exam_question_id' => $question->id,
    ]);



    $response = $this->actingAs($teacher)
        ->getJson(route('api.v1.exams.correction.show', [$exam->id, $session->id]));

    $response->assertOk()
        ->assertJsonStructure([
            'session' => [
                'id',
                'student' => [
                    'id',
                    'name',
                    'email',
                ],
            ],
            'answers' => [
                '*' => [
                    'id',
                    'question_content',
                    'student_answer',
                    'score_earned',
                ]
            ]
        ]);
});

test('teacher can update student answer score', function () {
    $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
    $exam = Exam::factory()->create();
    $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
    $session = ExamSession::factory()->create([
        'exam_id' => $exam->id,
        'user_id' => $student->id,
    ]);
    $question = ExamQuestion::factory()->create([
        'exam_id' => $exam->id,
        'score_value' => 10,
    ]);
    $detail = ExamResultDetail::factory()->create([
        'exam_session_id' => $session->id,
        'exam_question_id' => $question->id,
        'score_earned' => 0,
    ]);

    $response = $this->actingAs($teacher)
        ->putJson(route('api.v1.exams.correction.update', [$session->id, $detail->id]), [
            'score_earned' => 8,
            'correction_notes' => 'Good effort',
        ]);

    $response->assertOk();

    $detail->refresh();
    expect($detail->score_earned)->toBe(8.0)
        ->and($detail->correction_notes)->toBe('Good effort');
});

test('score cannot exceed max score', function () {
    $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
    $exam = Exam::factory()->create();
    $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
    $session = ExamSession::factory()->create([
        'exam_id' => $exam->id,
        'user_id' => $student->id,
    ]);
    $question = ExamQuestion::factory()->create([
        'exam_id' => $exam->id,
        'score_value' => 10,
    ]);
    $detail = ExamResultDetail::factory()->create([
        'exam_session_id' => $session->id,
        'exam_question_id' => $question->id,
    ]);

    $response = $this->actingAs($teacher)
        ->putJson(route('api.v1.exams.correction.update', [$session->id, $detail->id]), [
            'score_earned' => 15,
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'Score cannot exceed maximum score of 10']);
});

test('finish correction recalculates total score', function () {
    $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
    $exam = Exam::factory()->create();
    $student = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
    $session = ExamSession::factory()->create([
        'exam_id' => $exam->id,
        'user_id' => $student->id,
        'total_score' => 0,
        'is_corrected' => false,
    ]);

    $q1 = ExamQuestion::factory()->create(['exam_id' => $exam->id, 'score_value' => 10]);
    $q2 = ExamQuestion::factory()->create(['exam_id' => $exam->id, 'score_value' => 10]);

    ExamResultDetail::factory()->create([
        'exam_session_id' => $session->id,
        'exam_question_id' => $q1->id,
        'score_earned' => 5,
    ]);
    ExamResultDetail::factory()->create([
        'exam_session_id' => $session->id,
        'exam_question_id' => $q2->id,
        'score_earned' => 8,
    ]);

    $response = $this->actingAs($teacher)
        ->postJson(route('api.v1.exams.correction.finish', [$session->id]));

    $response->assertOk();

    $session->refresh();
    expect($session->total_score)->toBe(13.0)
        ->and($session->is_corrected)->toBeTrue();
});

test('teacher can fetch list of exam sessions', function () {
    $teacher = User::factory()->create(['user_type' => UserTypeEnum::TEACHER]);
    $exam = Exam::factory()->create();
    $student1 = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);
    $student2 = User::factory()->create(['user_type' => UserTypeEnum::STUDENT]);

    ExamSession::factory()->create(['exam_id' => $exam->id, 'user_id' => $student1->id]);
    ExamSession::factory()->create(['exam_id' => $exam->id, 'user_id' => $student2->id]);

    // Create another session for a different exam (should not be listed)
    ExamSession::factory()->create(['user_id' => $student1->id]);

    $response = $this->actingAs($teacher)
        ->getJson(route('api.v1.exams.correction.index', [$exam->id]));

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'student' => ['id', 'name'],
                    'total_score',
                    'is_finished',
                    'is_corrected',
                ]
            ]
        ]);
});
