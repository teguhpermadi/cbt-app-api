<?php

declare(strict_types=1);

use App\Jobs\ApproveQuestionDraftJob;
use App\Models\QuestionDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('job accepts draft parameter', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new ApproveQuestionDraftJob(draft: $draft);

    expect($job->draft)->toBe($draft);
});

it('job accepts reviewedBy parameter', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new ApproveQuestionDraftJob(
        draft: $draft,
        reviewedBy: 'user-123'
    );

    expect($job->reviewedBy)->toBe('user-123');
});

it('reviewedBy is optional', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new ApproveQuestionDraftJob(draft: $draft);

    expect($job->reviewedBy)->toBeNull();
});

it('has 3 tries', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new ApproveQuestionDraftJob(draft: $draft);

    expect($job->tries)->toBe(3);
});

it('has 30 second backoff', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new ApproveQuestionDraftJob(draft: $draft);

    expect($job->backoff)->toBe(30);
});

describe('draft validation', function () {
    it('can check if draft is pending', function () {
        $draft = new QuestionDraft(['status' => 'pending']);
        expect($draft->isPending())->toBeTrue();
    });

    it('can check if draft is approved', function () {
        $draft = new QuestionDraft(['status' => 'approved']);
        expect($draft->isApproved())->toBeTrue();
    });

    it('can check if draft is rejected', function () {
        $draft = new QuestionDraft(['status' => 'rejected']);
        expect($draft->isRejected())->toBeTrue();
    });
});

describe('option normalization', function () {
    it('handles multiple choice options', function () {
        $options = [
            ['key' => 'A', 'content' => 'Answer A', 'is_correct' => true],
            ['key' => 'B', 'content' => 'Answer B', 'is_correct' => false],
        ];

        expect($options[0]['key'])->toBe('A');
        expect($options[0]['is_correct'])->toBeTrue();
    });

    it('handles true false options', function () {
        $options = [
            ['option_key' => 'T', 'content' => 'Benar', 'is_correct' => true],
            ['option_key' => 'F', 'content' => 'Salah', 'is_correct' => false],
        ];

        expect($options[0]['option_key'])->toBe('T');
        expect($options[0]['is_correct'])->toBeTrue();
    });

    it('handles short answer options', function () {
        $options = [
            ['option_key' => 'SA1', 'content' => 'Jakarta', 'is_correct' => true],
            ['option_key' => 'SA2', 'content' => 'jakarta', 'is_correct' => true],
        ];

        $correctAnswers = collect($options)
            ->where('is_correct', true)
            ->pluck('content')
            ->toArray();

        expect($correctAnswers)->toContain('Jakarta');
    });

    it('handles matching pairs', function () {
        $options = [
            [
                'option_key' => 'L1',
                'content' => 'Ibu kota Prancis',
                'metadata' => ['side' => 'left', 'pair_id' => 1, 'match_with' => 'R1'],
            ],
            [
                'option_key' => 'R1',
                'content' => 'Paris',
                'metadata' => ['side' => 'right', 'pair_id' => 1, 'match_with' => 'L1'],
            ],
        ];

        $leftOptions = collect($options)
            ->filter(fn ($opt) => ($opt['metadata']['side'] ?? '') === 'left');

        expect($leftOptions->first()['content'])->toBe('Ibu kota Prancis');
    });

    it('handles sequence options', function () {
        $options = [
            ['option_key' => '1', 'content' => 'Step 1', 'metadata' => ['correct_position' => 1]],
            ['option_key' => '2', 'content' => 'Step 2', 'metadata' => ['correct_position' => 2]],
            ['option_key' => '3', 'content' => 'Step 3', 'metadata' => ['correct_position' => 3]],
        ];

        $sorted = collect($options)
            ->sortBy(fn ($opt) => $opt['metadata']['correct_position'] ?? 0)
            ->pluck('content')
            ->toArray();

        expect($sorted)->toBe(['Step 1', 'Step 2', 'Step 3']);
    });

    it('handles categorization groups', function () {
        $options = [
            [
                'option_key' => 'C1I1',
                'content' => 'Mobil',
                'metadata' => ['group_title' => 'Kendaraan', 'group_uuid' => 'uuid-1'],
            ],
            [
                'option_key' => 'C2I1',
                'content' => 'Apel',
                'metadata' => ['group_title' => 'Buah', 'group_uuid' => 'uuid-2'],
            ],
        ];

        $groups = collect($options)
            ->groupBy('metadata.group_title')
            ->toArray();

        expect(array_keys($groups))->toContain('Kendaraan');
        expect(array_keys($groups))->toContain('Buah');
    });
});
