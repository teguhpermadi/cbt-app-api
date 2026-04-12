<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class QuestionDraft extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'question_drafts';

    protected $fillable = [
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
        'reviewed_by',
    ];

    protected $casts = [
        'options' => 'array',
        'reviewed_at' => 'datetime',
        'status' => 'string',
    ];

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCurriculum($query, string $curriculumId)
    {
        return $query->where('curriculum_id', $curriculumId);
    }

    public function scopeBySubject($query, string $subjectCode)
    {
        return $query->where('subject_code', $subjectCode);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'multiple_choice' => 'Multiple Choice',
            'multiple_selection' => 'Multiple Selection',
            'true_false' => 'True/False',
            'short_answer' => 'Short Answer',
            'essay' => 'Essay',
            'math_input' => 'Math Input',
            'matching' => 'Matching',
            'sequence' => 'Sequence',
            'arrange_words' => 'Arrange Words',
            'categorization' => 'Categorization',
            'arabic_response' => 'Arabic Response',
            'javanese_response' => 'Javanese Response',
            default => $this->type,
        };
    }

    public function getDifficultyLabel(): string
    {
        return match ($this->difficulty) {
            'easy' => 'Easy',
            'medium' => 'Medium',
            'hard' => 'Hard',
            default => $this->difficulty,
        };
    }

    public function getCorrectAnswer(): array
    {
        return match ($this->type) {
            'multiple_choice', 'true_false' => collect($this->options)
                ->firstWhere('is_correct', true) ?? [],
            'multiple_selection' => collect($this->options)
                ->where('is_correct', true)
                ->values()
                ->toArray(),
            'matching' => [
                'pairs' => collect($this->options)
                    ->filter(fn ($opt) => str_starts_with($opt['option_key'] ?? '', 'L'))
                    ->mapWithKeys(fn ($opt) => [$opt['option_key'] => $opt['match_with'] ?? ''])
                    ->toArray(),
            ],
            'sequence' => [
                'order' => collect($this->options)
                    ->sortBy('correct_position')
                    ->pluck('option_key')
                    ->values()
                    ->toArray(),
            ],
            'short_answer', 'arabic_response', 'javanese_response' => [
                'answers' => collect($this->options)
                    ->where('is_correct', true)
                    ->pluck('content')
                    ->values()
                    ->toArray(),
            ],
            'essay' => [
                'rubric' => $this->options[0]['content'] ?? null,
            ],
            'math_input' => [
                'answer' => $this->options[0]['metadata']['correct_answer'] ?? null,
                'tolerance' => $this->options[0]['metadata']['tolerance'] ?? 0,
                'unit' => $this->options[0]['metadata']['unit'] ?? null,
            ],
            'arrange_words' => [
                'words' => ($opt = $this->options[0] ?? null)
                    ? ($opt['metadata']['shuffle_mode'] === 'alphabet'
                        ? mb_str_split(preg_replace('/\s/u', '', $opt['content']))
                        : explode($opt['metadata']['delimiter'] ?? ' ', $opt['content']))
                    : [],
            ],
            'categorization' => [
                'groups' => collect($this->options)
                    ->groupBy('metadata.group_title')
                    ->map(fn ($items, $title) => [
                        'title' => $title,
                        'items' => $items->pluck('option_key')->values()->toArray(),
                    ])
                    ->values()
                    ->toArray(),
            ],
            default => [],
        };
    }
}
