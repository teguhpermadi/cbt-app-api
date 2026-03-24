<?php

declare(strict_types=1);

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionTypeEnum;
use App\Jobs\GenerateQuestionJob;
use App\Models\Curriculum;
use App\Models\Taxonomy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('job has correct default values', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $job = new GenerateQuestionJob(
        curriculum: $curriculum,
        subjectCode: 'MAT',
        type: QuestionTypeEnum::MULTIPLE_CHOICE,
        difficulty: QuestionDifficultyLevelEnum::Easy
    );

    expect($job->subjectCode)->toBe('MAT');
    expect($job->type)->toBe(QuestionTypeEnum::MULTIPLE_CHOICE);
    expect($job->difficulty)->toBe(QuestionDifficultyLevelEnum::Easy);
    expect($job->count)->toBe(5);
    expect($job->model)->toBe('openai/gpt-4o-mini');
    expect($job->customMaterial)->toBeNull();
    expect($job->taxonomy)->toBeNull();
    expect($job->userId)->toBeNull();
});

it('job accepts custom count', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $job = new GenerateQuestionJob(
        curriculum: $curriculum,
        subjectCode: 'MAT',
        type: QuestionTypeEnum::MULTIPLE_CHOICE,
        difficulty: QuestionDifficultyLevelEnum::Easy,
        count: 10
    );

    expect($job->count)->toBe(10);
});

it('job accepts custom model', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $job = new GenerateQuestionJob(
        curriculum: $curriculum,
        subjectCode: 'MAT',
        type: QuestionTypeEnum::MULTIPLE_CHOICE,
        difficulty: QuestionDifficultyLevelEnum::Easy,
        model: 'meta-llama/llama-3.1-8b-instruct'
    );

    expect($job->model)->toBe('meta-llama/llama-3.1-8b-instruct');
});

it('job accepts custom material', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $customMaterial = 'Materi tambahan tentang aljabar';

    $job = new GenerateQuestionJob(
        curriculum: $curriculum,
        subjectCode: 'MAT',
        type: QuestionTypeEnum::MULTIPLE_CHOICE,
        difficulty: QuestionDifficultyLevelEnum::Easy,
        customMaterial: $customMaterial
    );

    expect($job->customMaterial)->toBe($customMaterial);
});

it('job accepts taxonomy', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $taxonomy = new Taxonomy([
        'taxonomy_type' => 'anderson_krathwohl',
        'code' => 'CP2',
        'name' => 'Understand',
    ]);

    $job = new GenerateQuestionJob(
        curriculum: $curriculum,
        subjectCode: 'MAT',
        type: QuestionTypeEnum::MULTIPLE_CHOICE,
        difficulty: QuestionDifficultyLevelEnum::Easy,
        taxonomy: $taxonomy
    );

    expect($job->taxonomy)->toBe($taxonomy);
});

it('job accepts user id', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $job = new GenerateQuestionJob(
        curriculum: $curriculum,
        subjectCode: 'MAT',
        type: QuestionTypeEnum::MULTIPLE_CHOICE,
        difficulty: QuestionDifficultyLevelEnum::Easy,
        userId: 'user-123'
    );

    expect($job->userId)->toBe('user-123');
});

it('job accepts all question types', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $types = QuestionTypeEnum::cases();

    foreach ($types as $type) {
        $job = new GenerateQuestionJob(
            curriculum: $curriculum,
            subjectCode: 'MAT',
            type: $type,
            difficulty: QuestionDifficultyLevelEnum::Easy
        );

        expect($job->type)->toBe($type);
    }
});

it('job accepts all difficulty levels', function () {
    $curriculum = new Curriculum([
        'name' => 'Test Curriculum',
        'code' => 'TEST',
        'phase' => 'Fase A',
        'level' => 'SD',
        'curriculum_type' => 'National',
    ]);
    $curriculum->_id = 'curriculum-1';

    $difficulties = [
        QuestionDifficultyLevelEnum::Easy,
        QuestionDifficultyLevelEnum::Medium,
        QuestionDifficultyLevelEnum::Hard,
    ];

    foreach ($difficulties as $difficulty) {
        $job = new GenerateQuestionJob(
            curriculum: $curriculum,
            subjectCode: 'MAT',
            type: QuestionTypeEnum::MULTIPLE_CHOICE,
            difficulty: $difficulty
        );

        expect($job->difficulty)->toBe($difficulty);
    }
});

describe('job configuration', function () {
    it('has 3 tries', function () {
        $curriculum = new Curriculum([
            'name' => 'Test',
        ]);
        $curriculum->_id = 'curriculum-1';

        $job = new GenerateQuestionJob(
            curriculum: $curriculum,
            subjectCode: 'MAT',
            type: QuestionTypeEnum::MULTIPLE_CHOICE,
            difficulty: QuestionDifficultyLevelEnum::Easy
        );

        expect($job->tries)->toBe(3);
    });

    it('has 60 second backoff', function () {
        $curriculum = new Curriculum([
            'name' => 'Test',
        ]);
        $curriculum->_id = 'curriculum-1';

        $job = new GenerateQuestionJob(
            curriculum: $curriculum,
            subjectCode: 'MAT',
            type: QuestionTypeEnum::MULTIPLE_CHOICE,
            difficulty: QuestionDifficultyLevelEnum::Easy
        );

        expect($job->backoff)->toBe(60);
    });
});
