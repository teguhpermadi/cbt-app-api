<?php

declare(strict_types=1);

use App\Enums\QuestionTypeEnum;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsAdmin();
});

describe('Option Management', function () {
    it('can list options', function () {
        $q = Question::factory()->withoutOptions()->create();
        Option::factory()->count(3)->create(['question_id' => $q->id]);

        $response = $this->getJson("/api/v1/options?question_id={$q->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data');
    });

    it('can filter options by question_id', function () {
        $q1 = Question::factory()->withoutOptions()->create();
        $q2 = Question::factory()->withoutOptions()->create();

        $o1 = Option::factory()->count(2)->create(['question_id' => $q1->id]);
        $o2 = Option::factory()->count(3)->create(['question_id' => $q2->id]);

        $response = $this->getJson("/api/v1/options?question_id={$q1->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.data'))->pluck('id');
        expect($ids)->toHaveCount(2);
        expect($ids)->toContain($o1[0]->id);
        expect($ids)->not->toContain($o2[0]->id);
    });

    it('can show an option', function () {
        $option = Option::factory()->create();

        $response = $this->getJson("/api/v1/options/{$option->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $option->id,
                    'content' => $option->content,
                ],
            ]);
    });

    it('validates TRUE_FALSE option_key to be T or F', function () {
        $question = Question::factory()->withoutOptions()->create(['type' => QuestionTypeEnum::TRUE_FALSE]);

        $response = $this->postJson('/api/v1/options', [
            'question_id' => $question->id,
            'option_key' => 'A', // Invalid for True/False
            'content' => 'Some content',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['option_key']);

        $response = $this->postJson('/api/v1/options', [
            'question_id' => $question->id,
            'option_key' => 'T', // Valid
            'content' => 'Some content',
        ]);

        $response->assertStatus(201);
    });

    it('validates MATCHING metadata requirements', function () {
        $question = Question::factory()->withoutOptions()->create(['type' => QuestionTypeEnum::MATCHING]);

        $response = $this->postJson('/api/v1/options', [
            'question_id' => $question->id,
            'option_key' => 'L1',
            'content' => 'Left item',
            'metadata' => ['side' => 'top'] // Invalid side
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['metadata.side']);

        $response = $this->postJson('/api/v1/options', [
            'question_id' => $question->id,
            'option_key' => 'L1',
            'content' => 'Left item',
            'metadata' => [
                'side' => 'left',
                'pair_id' => 1,
                'match_with' => 'R1'
            ]
        ]);

        $response->assertStatus(201);
    });

    it('can update an option and its metadata', function () {
        $question = Question::factory()->withoutOptions()->create(['type' => QuestionTypeEnum::SEQUENCE]);
        $option = Option::factory()->create(['question_id' => $question->id]);

        $data = [
            'content' => 'Updated content',
            'metadata' => ['correct_position' => 5]
        ];

        $response = $this->putJson("/api/v1/options/{$option->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('options', [
            'id' => $option->id,
            'content' => 'Updated content',
        ]);

        $this->assertEquals(5, $option->fresh()->getMetadata('correct_position'));
    });

    it('can soft-delete an option', function () {
        $option = Option::factory()->create();

        $response = $this->deleteJson("/api/v1/options/{$option->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($option);
    });
});

describe('Option Media Management', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('can upload media to an option', function () {
        $option = Option::factory()->create();
        $file = UploadedFile::fake()->image('option_img.png');

        $response = $this->postJson("/api/v1/options/{$option->id}/media", [
            'media' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media uploaded successfully',
            ]);

        $this->assertCount(1, $option->fresh()->getMedia('option_media'));
    });

    it('can replace media in an option', function () {
        $option = Option::factory()->create();
        $file1 = UploadedFile::fake()->image('old_opt.jpg');
        $media = $option->addMedia($file1)->toMediaCollection('option_media');

        $file2 = UploadedFile::fake()->image('new_opt.jpg');
        $response = $this->postJson("/api/v1/options/{$option->id}/media/{$media->id}", [
            'media' => $file2,
        ]);

        $response->assertStatus(200);
        $this->assertCount(1, $option->fresh()->getMedia('option_media'));
    });

    it('can delete media from an option', function () {
        $option = Option::factory()->create();
        $file = UploadedFile::fake()->image('opt.jpg');
        $media = $option->addMedia($file)->toMediaCollection('option_media');

        $response = $this->deleteJson("/api/v1/options/{$option->id}/media/{$media->id}");

        $response->assertStatus(200);
        $this->assertCount(0, $option->fresh()->getMedia('option_media'));
    });
});
