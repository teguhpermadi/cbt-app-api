<?php

declare(strict_types=1);

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionScoreEnum;
use App\Enums\QuestionTimeEnum;
use App\Enums\QuestionTypeEnum;
use App\Enums\UserTypeEnum;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
});

describe('Question Management', function () {
    it('can list questions', function () {
        Question::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/questions');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data')
            ->assertJson([
                'success' => true,
                'message' => 'Questions retrieved successfully',
            ]);
    });

    it('can show a question', function () {
        $question = Question::factory()->create();

        $response = $this->getJson("/api/v1/questions/{$question->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $question->id,
                    'content' => $question->content,
                ],
            ]);
    });

    it('can create a question', function () {
        $data = [
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE->value,
            'difficulty' => QuestionDifficultyLevelEnum::Easy->value,
            'timer' => QuestionTimeEnum::TEN_SECONDS->value,
            'content' => 'What is the capital of Indonesia?',
            'score' => QuestionScoreEnum::ONE->value,
            'hint' => 'Starts with J',
            'order' => 1,
            'is_approved' => true,
            'tags' => ['geography', 'capitals'],
        ];

        $response = $this->postJson('/api/v1/questions', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Question created successfully',
            ]);

        $this->assertDatabaseHas('questions', [
            'content' => 'What is the capital of Indonesia?',
            'user_id' => $this->admin->id,
        ]);
    });

    it('can update a question', function () {
        $question = Question::factory()->create();
        $data = ['content' => 'Updated Question Content'];

        $response = $this->putJson("/api/v1/questions/{$question->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Question updated successfully',
            ]);

        $this->assertDatabaseHas('questions', [
            'id' => $question->id,
            'content' => 'Updated Question Content',
        ]);
    });

    it('can soft-delete a question', function () {
        $question = Question::factory()->create();

        $response = $this->deleteJson("/api/v1/questions/{$question->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($question);
    });

    it('can list trashed questions', function () {
        $question = Question::factory()->create();
        $question->delete();

        $response = $this->getJson('/api/v1/questions/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a question', function () {
        $question = Question::factory()->create();
        $question->delete();

        $response = $this->postJson("/api/v1/questions/{$question->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($question);
    });

    it('can force delete a question', function () {
        $question = Question::factory()->create();
        $question->delete();

        $response = $this->deleteJson("/api/v1/questions/{$question->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);
    });

    it('can bulk delete questions', function () {
        $questions = Question::factory()->count(3)->create();
        $ids = $questions->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/questions/bulk-delete', ['ids' => $ids]);

        $response->assertStatus(200);
        foreach ($questions as $question) {
            $this->assertSoftDeleted($question);
        }
    });

    it('can bulk update questions', function () {
        $questions = Question::factory()->count(2)->create();
        $data = [
            'questions' => [
                ['id' => $questions[0]->id, 'order' => 10],
                ['id' => $questions[1]->id, 'order' => 20],
            ],
        ];

        $response = $this->postJson('/api/v1/questions/bulk-update', $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('questions', ['id' => $questions[0]->id, 'order' => 10]);
        $this->assertDatabaseHas('questions', ['id' => $questions[1]->id, 'order' => 20]);
    });

    it('can create a question with options', function () {
        $data = [
            'type' => QuestionTypeEnum::MULTIPLE_CHOICE->value,
            'difficulty' => QuestionDifficultyLevelEnum::Medium->value,
            'timer' => QuestionTimeEnum::TEN_SECONDS->value,
            'content' => 'Question with options?',
            'score' => QuestionScoreEnum::FIVE->value,
            'options' => [
                ['option_key' => 'A', 'content' => 'Option A', 'is_correct' => true],
                ['option_key' => 'B', 'content' => 'Option B', 'is_correct' => false],
            ],
        ];

        $response = $this->postJson('/api/v1/questions', $data);

        $response->assertStatus(201);

        $question = Question::where('content', 'Question with options?')->first();
        $this->assertNotNull($question);
        $this->assertCount(2, $question->options);
        $this->assertDatabaseHas('options', ['question_id' => $question->id, 'option_key' => 'A', 'is_correct' => true]);
    });

    it('can update a question with options', function () {
        $question = Question::factory()->withoutOptions()->create(['type' => QuestionTypeEnum::MULTIPLE_CHOICE->value]);

        // Create initial options
        \App\Models\Option::createMultipleChoiceOptions($question->id, [
            ['key' => 'A', 'content' => 'Old A', 'is_correct' => true],
            ['key' => 'B', 'content' => 'Old B', 'is_correct' => false],
        ]);

        $data = [
            'content' => 'Updated Content',
            'options' => [
                ['option_key' => 'A', 'content' => 'New A', 'is_correct' => false],
                ['option_key' => 'B', 'content' => 'New B', 'is_correct' => true],
                ['option_key' => 'C', 'content' => 'New C', 'is_correct' => false],
            ],
        ];

        $response = $this->putJson("/api/v1/questions/{$question->id}", $data);

        $response->assertStatus(200);

        $this->assertCount(3, $question->fresh()->options);
        $this->assertDatabaseHas('options', ['question_id' => $question->id, 'content' => 'New A']);

        // Check that Old A is soft deleted
        $this->assertSoftDeleted('options', ['question_id' => $question->id, 'content' => 'Old A']);
    });
});

describe('Question Media Management', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can upload media to a question', function () {
        $question = Question::factory()->create();
        $question->clearMediaCollection('question_content');
        $file = UploadedFile::fake()->image('test.jpg');

        $response = $this->postJson("/api/v1/questions/{$question->id}/media", [
            'media' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media uploaded successfully',
            ]);

        $this->assertCount(1, $question->fresh()->getMedia('question_content'));
    });

    it('can replace media in a question', function () {
        $question = Question::factory()->create();
        $question->clearMediaCollection('question_content');
        $file1 = UploadedFile::fake()->image('old.jpg');
        $media = $question->addMedia($file1)->toMediaCollection('question_content');

        $file2 = UploadedFile::fake()->image('new.jpg');
        $response = $this->postJson("/api/v1/questions/{$question->id}/media/{$media->id}", [
            'media' => $file2,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media replaced successfully',
            ]);

        $this->assertCount(1, $question->fresh()->getMedia('question_content'));
        $this->assertNotEquals($media->id, $response->json('data.id'));
    });

    it('can delete media from a question', function () {
        $question = Question::factory()->create();
        $question->clearMediaCollection('question_content');
        $file = UploadedFile::fake()->image('test.jpg');
        $media = $question->addMedia($file)->toMediaCollection('question_content');

        $response = $this->deleteJson("/api/v1/questions/{$question->id}/media/{$media->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);

        $this->assertCount(0, $question->fresh()->getMedia('question_content'));
    });
});
