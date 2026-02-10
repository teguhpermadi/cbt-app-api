<?php

declare(strict_types=1);

use App\Enums\UserTypeEnum;
use App\Models\QuestionBank;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'user_type' => UserTypeEnum::ADMIN,
    ]);
    $this->actingAs($this->admin, 'sanctum');
    $this->subject = Subject::factory()->create();
});

describe('Question Bank Management', function () {
    it('can list question banks', function () {
        QuestionBank::factory()->count(3)->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/question-banks');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.data')
            ->assertJson([
                'success' => true,
                'message' => 'Question banks retrieved successfully',
            ]);
    });

    it('can show a question bank', function () {
        $bank = QuestionBank::factory()->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->getJson("/api/v1/question-banks/{$bank->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $bank->id,
                    'name' => $bank->name,
                ],
            ]);
    });

    it('can create a question bank', function () {
        $data = [
            'name' => 'Grade 10 Mathematics Bank',
            'subject_id' => $this->subject->id,
        ];

        $response = $this->postJson('/api/v1/question-banks', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Question bank created successfully',
            ]);

        $this->assertDatabaseHas('question_banks', [
            'name' => 'Grade 10 Mathematics Bank',
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);
    });

    it('can update a question bank', function () {
        $bank = QuestionBank::factory()->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);
        $data = ['name' => 'Updated Bank Name'];

        $response = $this->putJson("/api/v1/question-banks/{$bank->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Question bank updated successfully',
            ]);

        $this->assertDatabaseHas('question_banks', [
            'id' => $bank->id,
            'name' => 'Updated Bank Name',
        ]);
    });

    it('can soft-delete a question bank', function () {
        $bank = QuestionBank::factory()->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->deleteJson("/api/v1/question-banks/{$bank->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted($bank);
    });

    it('can list trashed question banks', function () {
        $bank = QuestionBank::factory()->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);
        $bank->delete();

        $response = $this->getJson('/api/v1/question-banks/trashed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data');
    });

    it('can restore a question bank', function () {
        $bank = QuestionBank::factory()->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);
        $bank->delete();

        $response = $this->postJson("/api/v1/question-banks/{$bank->id}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted($bank);
    });

    it('can force delete a question bank', function () {
        $bank = QuestionBank::factory()->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);
        $bank->delete();

        $response = $this->deleteJson("/api/v1/question-banks/{$bank->id}/force-delete");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('question_banks', ['id' => $bank->id]);
    });

    it('can show question bank with questions', function () {
        $bank = QuestionBank::factory()->create([
            'subject_id' => $this->subject->id,
            'user_id' => $this->admin->id,
        ]);

        $questions = Question::factory()->count(5)->create();
        $bank->questions()->attach($questions->pluck('id'));

        $response = $this->getJson("/api/v1/question-banks/{$bank->id}");

        $response->assertStatus(200)
            ->assertJsonCount(15, 'data.questions')
            ->assertJsonPath('data.questions_count', 15);
    });
});
