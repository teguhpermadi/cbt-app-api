<?php

declare(strict_types=1);

use App\Enums\QuestionSuggestionStateEnum;
use App\Enums\UserTypeEnum;
use App\Models\Question;
use App\Models\QuestionSuggestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->teacher = User::factory()->create([
        'user_type' => UserTypeEnum::TEACHER,
    ]);
    $this->student = User::factory()->create([
        'user_type' => UserTypeEnum::STUDENT,
    ]);
});

describe('Question Suggestion Management', function () {
    it('can create a suggestion', function () {
        $this->actingAs($this->teacher, 'sanctum');
        $question = Question::factory()->create();

        $data = [
            'question_id' => $question->id,
            'description' => 'Fix typo in content',
            'data' => ['content' => 'Corrected content'],
        ];

        $response = $this->postJson('/api/v1/question-suggestions', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Question suggestion created successfully',
            ]);

        $this->assertDatabaseHas('question_suggestions', [
            'question_id' => $question->id,
            'user_id' => $this->teacher->id,
            'state' => QuestionSuggestionStateEnum::PENDING->value,
            'description' => 'Fix typo in content',
        ]);
    });

    it('can list suggestions', function () {
        $this->actingAs($this->admin, 'sanctum');
        QuestionSuggestion::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/question-suggestions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data')
            ->assertJson([
                'success' => true,
                'message' => 'Question suggestions retrieved successfully',
            ]);
    });

    it('can list my suggestions', function () {
        $this->actingAs($this->teacher, 'sanctum');
        QuestionSuggestion::factory()->count(2)->create(['user_id' => $this->teacher->id]);
        QuestionSuggestion::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->getJson('/api/v1/question-suggestions/mine');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data')
            ->assertJson([
                'success' => true,
                'message' => 'My suggestions retrieved successfully',
            ]);
    });

    it('can show a suggestion', function () {
        $this->actingAs($this->admin, 'sanctum');
        $suggestion = QuestionSuggestion::factory()->create();

        $response = $this->getJson("/api/v1/question-suggestions/{$suggestion->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $suggestion->id,
                    'description' => $suggestion->description,
                ],
            ]);
    });

    it('can update pending suggestion', function () {
        $this->actingAs($this->teacher, 'sanctum');
        $suggestion = QuestionSuggestion::factory()->create([
            'user_id' => $this->teacher->id,
            'state' => QuestionSuggestionStateEnum::PENDING,
        ]);

        $data = [
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/v1/question-suggestions/{$suggestion->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Question suggestion updated successfully',
            ]);

        $this->assertDatabaseHas('question_suggestions', [
            'id' => $suggestion->id,
            'description' => 'Updated description',
        ]);
    });

    it('cannot update approved suggestion', function () {
        $this->actingAs($this->teacher, 'sanctum');
        $suggestion = QuestionSuggestion::factory()->create([
            'user_id' => $this->teacher->id,
            'state' => QuestionSuggestionStateEnum::APPROVED,
        ]);

        $response = $this->putJson("/api/v1/question-suggestions/{$suggestion->id}", ['description' => 'Try update']);

        $response->assertStatus(400); // Or 422 depending on error implementation
    });

    it('can delete own suggestion', function () {
        $this->actingAs($this->teacher, 'sanctum');
        $suggestion = QuestionSuggestion::factory()->create([
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->deleteJson("/api/v1/question-suggestions/{$suggestion->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($suggestion);
    });

    it('admin can delete any suggestion', function () {
        $this->actingAs($this->admin, 'sanctum');
        $suggestion = QuestionSuggestion::factory()->create([
            'user_id' => $this->teacher->id,
        ]);

        $response = $this->deleteJson("/api/v1/question-suggestions/{$suggestion->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($suggestion);
    });

    it('can approve suggestion', function () {
        $this->actingAs($this->teacher, 'sanctum');
        $question = Question::factory()->create(['user_id' => $this->teacher->id, 'content' => 'Original Content']);
        $suggestion = QuestionSuggestion::factory()->create([
            'question_id' => $question->id,
            'state' => QuestionSuggestionStateEnum::PENDING,
            'data' => ['content' => 'Updated Content'],
        ]);

        $response = $this->postJson("/api/v1/question-suggestions/{$suggestion->id}/approve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('question_suggestions', [
            'id' => $suggestion->id,
            'state' => QuestionSuggestionStateEnum::APPROVED->value,
        ]);

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'content' => 'Updated Content',
        ]);
    });

    it('can reject suggestion', function () {
        $this->actingAs($this->teacher, 'sanctum');
        $question = Question::factory()->create(['user_id' => $this->teacher->id]);
        $suggestion = QuestionSuggestion::factory()->create([
            'question_id' => $question->id,
            'state' => QuestionSuggestionStateEnum::PENDING,
        ]);

        $response = $this->postJson("/api/v1/question-suggestions/{$suggestion->id}/reject");

        $response->assertStatus(200);

        $this->assertDatabaseHas('question_suggestions', [
            'id' => $suggestion->id,
            'state' => QuestionSuggestionStateEnum::REJECTED->value,
        ]);
    });

    it('non-owner cannot approve or reject suggestion', function () {
        $this->actingAs($this->student, 'sanctum'); // Or another teacher
        $question = Question::factory()->create(['user_id' => $this->teacher->id]);
        $suggestion = QuestionSuggestion::factory()->create([
            'question_id' => $question->id,
            'state' => QuestionSuggestionStateEnum::PENDING,
        ]);

        $responseApprove = $this->postJson("/api/v1/question-suggestions/{$suggestion->id}/approve");
        $responseApprove->assertStatus(401);

        $responseReject = $this->postJson("/api/v1/question-suggestions/{$suggestion->id}/reject");
        $responseReject->assertStatus(401);
    });
});
