<?php

declare(strict_types=1);

use App\Jobs\RejectQuestionDraftJob;
use App\Models\QuestionDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('job accepts required parameters', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new RejectQuestionDraftJob(
        draft: $draft,
        reason: 'Soal tidak sesuai kurikulum'
    );

    expect($job->draft)->toBe($draft);
    expect($job->reason)->toBe('Soal tidak sesuai kurikulum');
});

it('job accepts reviewedBy parameter', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new RejectQuestionDraftJob(
        draft: $draft,
        reason: 'Soal tidak sesuai kurikulum',
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

    $job = new RejectQuestionDraftJob(
        draft: $draft,
        reason: 'Soal tidak cocok'
    );

    expect($job->reviewedBy)->toBeNull();
});

it('has 3 tries', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new RejectQuestionDraftJob(
        draft: $draft,
        reason: 'Test reason'
    );

    expect($job->tries)->toBe(3);
});

it('has 15 second backoff', function () {
    $draft = new QuestionDraft([
        'type' => 'multiple_choice',
        'content' => 'Test question',
        'options' => [],
        'status' => 'pending',
    ]);

    $job = new RejectQuestionDraftJob(
        draft: $draft,
        reason: 'Test reason'
    );

    expect($job->backoff)->toBe(15);
});

describe('draft status checking', function () {
    it('can check pending status', function () {
        $draft = new QuestionDraft(['status' => 'pending']);
        expect($draft->isPending())->toBeTrue();
    });

    it('can check approved status', function () {
        $draft = new QuestionDraft(['status' => 'approved']);
        expect($draft->isApproved())->toBeTrue();
    });

    it('can check rejected status', function () {
        $draft = new QuestionDraft(['status' => 'rejected']);
        expect($draft->isRejected())->toBeTrue();
    });

    it('rejected draft is not pending', function () {
        $draft = new QuestionDraft(['status' => 'rejected']);
        expect($draft->isPending())->toBeFalse();
    });

    it('approved draft is not pending', function () {
        $draft = new QuestionDraft(['status' => 'approved']);
        expect($draft->isPending())->toBeFalse();
    });
});

describe('rejection reasons', function () {
    it('accepts various rejection reasons', function () {
        $reasons = [
            'Soal tidak sesuai kurikulum',
            'Tingkat kesulitan tidak sesuai',
            'Jawaban kurang tepat',
            'Konten tidak jelas',
            'Format soal salah',
        ];

        $draft = new QuestionDraft([
            'type' => 'multiple_choice',
            'content' => 'Test',
            'options' => [],
            'status' => 'pending',
        ]);

        foreach ($reasons as $reason) {
            $job = new RejectQuestionDraftJob(
                draft: $draft,
                reason: $reason
            );
            expect($job->reason)->toBe($reason);
        }
    });

    it('reason can be empty string', function () {
        $draft = new QuestionDraft([
            'type' => 'multiple_choice',
            'content' => 'Test',
            'options' => [],
            'status' => 'pending',
        ]);

        $job = new RejectQuestionDraftJob(
            draft: $draft,
            reason: ''
        );

        expect($job->reason)->toBe('');
    });
});
