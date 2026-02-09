<?php

use App\Models\Question;
use App\Models\User;
use App\Enums\QuestionTypeEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a question with options', function () {
    $user = User::factory()->create();
    $question = Question::factory()->create(['user_id' => $user->id]);

    expect(Question::where('id', $question->id)->exists())->toBeTrue()
        ->and($question->user_id)->toBe($user->id)
        ->and($question->options()->count())->toBeGreaterThan(0);
});

it('can create multiple choice question', function () {
    $question = Question::factory()->withType(QuestionTypeEnum::MULTIPLE_CHOICE)->create();

    expect($question->type)->toBe(QuestionTypeEnum::MULTIPLE_CHOICE)
        ->and($question->options()->count())->toBe(4)
        ->and($question->options()->where('is_correct', true)->count())->toBe(1);
});

it('can create true false question', function () {
    $question = Question::factory()->withType(QuestionTypeEnum::TRUE_FALSE)->create();

    expect($question->type)->toBe(QuestionTypeEnum::TRUE_FALSE)
        ->and($question->options()->count())->toBe(2)
        ->and($question->options()->where('is_correct', true)->count())->toBe(1);
});

it('can create matching question', function () {
    $question = Question::factory()->withType(QuestionTypeEnum::MATCHING)->create();
    dump("Matching options count: " . $question->options()->count());

    expect($question->type)->toBe(QuestionTypeEnum::MATCHING)
        ->and($question->options()->count())->toBe(8); // 4 pairs * 2 sides
});

it('can create sequence question', function () {
    $question = Question::factory()->withType(QuestionTypeEnum::SEQUENCE)->create();
    dump("Sequence options count: " . $question->options()->count());

    expect($question->type)->toBe(QuestionTypeEnum::SEQUENCE)
        ->and($question->options()->count())->toBe(4);
});

it('can create math input question', function () {
    $question = Question::factory()->withType(QuestionTypeEnum::MATH_INPUT)->create();

    expect($question->type)->toBe(QuestionTypeEnum::MATH_INPUT)
        ->and($question->options()->count())->toBe(1)
        ->and($question->options()->first()->is_correct)->toBeTrue();
});

it('can create essay question', function () {
    $question = Question::factory()->withType(QuestionTypeEnum::ESSAY)->create();

    expect($question->type)->toBe(QuestionTypeEnum::ESSAY)
        ->and($question->options()->count())->toBe(1)
        ->and($question->options()->first()->content)->toContain('Rubrik Penilaian:');
});
