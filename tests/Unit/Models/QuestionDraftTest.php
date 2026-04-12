<?php

declare(strict_types=1);

use App\Models\QuestionDraft;
use Illuminate\Database\Eloquent\Builder;

it('has correct connection', function () {
    $draft = new QuestionDraft();
    expect($draft->getConnectionName())->toBe('mysql');
});

it('has correct collection name', function () {
    $draft = new QuestionDraft();
    expect($draft->getTable())->toBe('question_drafts');
});

it('has fillable attributes', function () {
    $draft = new QuestionDraft();
    expect($draft->getFillable())->toContain(
        'user_id',
        'curriculum_id',
        'subject_code',
        'type',
        'difficulty',
        'timer',
        'score',
        'content',
        'hint',
        'taxonomy_type',
        'taxonomy_code',
        'custom_material',
        'options',
        'status',
        'generated_by',
        'generation_prompt',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by'
    );
});

it('casts options to array', function () {
    $options = [
        ['key' => 'A', 'content' => 'Option A', 'is_correct' => true],
        ['key' => 'B', 'content' => 'Option B', 'is_correct' => false],
    ];
    $draft = new QuestionDraft(['options' => $options]);
    expect($draft->options)->toBeArray();
    expect($draft->options)->toHaveCount(2);
});

it('casts status to string', function () {
    $draft = new QuestionDraft(['status' => 'pending']);
    expect($draft->status)->toBe('pending');
});

it('has correct status constants', function () {
    expect(QuestionDraft::STATUS_PENDING)->toBe('pending');
    expect(QuestionDraft::STATUS_APPROVED)->toBe('approved');
    expect(QuestionDraft::STATUS_REJECTED)->toBe('rejected');
});

describe('scopes', function () {
    it('pending scope returns builder', function () {
        $query = QuestionDraft::pending();
        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('approved scope returns builder', function () {
        $query = QuestionDraft::approved();
        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('rejected scope returns builder', function () {
        $query = QuestionDraft::rejected();
        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('byUser scope returns builder', function () {
        $query = QuestionDraft::byUser('user-123');
        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('byCurriculum scope returns builder', function () {
        $query = QuestionDraft::byCurriculum('curriculum-123');
        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('bySubject scope returns builder', function () {
        $query = QuestionDraft::bySubject('MAT');
        expect($query)->toBeInstanceOf(Builder::class);
    });

    it('byType scope returns builder', function () {
        $query = QuestionDraft::byType('multiple_choice');
        expect($query)->toBeInstanceOf(Builder::class);
    });
});

describe('helper methods', function () {
    it('isPending returns true for pending status', function () {
        $draft = new QuestionDraft(['status' => 'pending']);
        expect($draft->isPending())->toBeTrue();
    });

    it('isPending returns false for non-pending status', function () {
        $draft = new QuestionDraft(['status' => 'approved']);
        expect($draft->isPending())->toBeFalse();
    });

    it('isApproved returns true for approved status', function () {
        $draft = new QuestionDraft(['status' => 'approved']);
        expect($draft->isApproved())->toBeTrue();
    });

    it('isApproved returns false for non-approved status', function () {
        $draft = new QuestionDraft(['status' => 'pending']);
        expect($draft->isApproved())->toBeFalse();
    });

    it('isRejected returns true for rejected status', function () {
        $draft = new QuestionDraft(['status' => 'rejected']);
        expect($draft->isRejected())->toBeTrue();
    });

    it('isRejected returns false for non-rejected status', function () {
        $draft = new QuestionDraft(['status' => 'pending']);
        expect($draft->isRejected())->toBeFalse();
    });
});

describe('getCorrectAnswer', function () {
    it('returns correct answer for multiple choice', function () {
        $options = [
            ['option_key' => 'A', 'content' => 'Option A', 'is_correct' => true],
            ['option_key' => 'B', 'content' => 'Option B', 'is_correct' => false],
            ['option_key' => 'C', 'content' => 'Option C', 'is_correct' => false],
            ['option_key' => 'D', 'content' => 'Option D', 'is_correct' => false],
        ];
        $draft = new QuestionDraft(['type' => 'multiple_choice', 'options' => $options]);
        $result = $draft->getCorrectAnswer();

        expect($result['option_key'])->toBe('A');
    });

    it('returns correct answers for multiple selection', function () {
        $options = [
            ['option_key' => 'A', 'content' => 'Option A', 'is_correct' => true],
            ['option_key' => 'B', 'content' => 'Option B', 'is_correct' => true],
            ['option_key' => 'C', 'content' => 'Option C', 'is_correct' => false],
            ['option_key' => 'D', 'content' => 'Option D', 'is_correct' => false],
        ];
        $draft = new QuestionDraft(['type' => 'multiple_selection', 'options' => $options]);
        $result = $draft->getCorrectAnswer();

        expect($result)->toHaveCount(2);
    });

    it('returns correct answer for short answer', function () {
        $options = [
            ['option_key' => 'SA1', 'content' => 'Jakarta', 'is_correct' => true],
            ['option_key' => 'SA2', 'content' => 'jakarta', 'is_correct' => true],
        ];
        $draft = new QuestionDraft(['type' => 'short_answer', 'options' => $options]);
        $result = $draft->getCorrectAnswer();

        expect($result['answers'])->toContain('Jakarta');
    });
});

describe('getTypeLabel', function () {
    it('returns correct label for multiple_choice', function () {
        $draft = new QuestionDraft(['type' => 'multiple_choice']);
        expect($draft->getTypeLabel())->toBe('Multiple Choice');
    });

    it('returns correct label for true_false', function () {
        $draft = new QuestionDraft(['type' => 'true_false']);
        expect($draft->getTypeLabel())->toBe('True/False');
    });

    it('returns correct label for essay', function () {
        $draft = new QuestionDraft(['type' => 'essay']);
        expect($draft->getTypeLabel())->toBe('Essay');
    });

    it('returns original value for unknown type', function () {
        $draft = new QuestionDraft(['type' => 'unknown_type']);
        expect($draft->getTypeLabel())->toBe('unknown_type');
    });
});

describe('getDifficultyLabel', function () {
    it('returns correct label for easy', function () {
        $draft = new QuestionDraft(['difficulty' => 'easy']);
        expect($draft->getDifficultyLabel())->toBe('Easy');
    });

    it('returns correct label for medium', function () {
        $draft = new QuestionDraft(['difficulty' => 'medium']);
        expect($draft->getDifficultyLabel())->toBe('Medium');
    });

    it('returns correct label for hard', function () {
        $draft = new QuestionDraft(['difficulty' => 'hard']);
        expect($draft->getDifficultyLabel())->toBe('Hard');
    });

    it('returns original value for unknown difficulty', function () {
        $draft = new QuestionDraft(['difficulty' => 'unknown']);
        expect($draft->getDifficultyLabel())->toBe('unknown');
    });
});
