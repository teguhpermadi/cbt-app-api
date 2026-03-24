<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionScoreEnum;
use App\Enums\QuestionTimeEnum;
use App\Enums\QuestionTypeEnum;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class ApproveQuestionDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public QuestionDraft $draft,
        public ?string $reviewedBy = null
    ) {}

    public function handle(): Question
    {
        if ($this->draft->isApproved()) {
            throw new InvalidArgumentException('Draft already approved');
        }

        return DB::transaction(function () {
            $question = $this->createQuestion();
            $this->createOptions($question);
            $this->updateDraftStatus();

            Log::info('QuestionDraft approved', [
                'draft_id' => $this->draft->id,
                'question_id' => $question->id,
                'type' => $this->draft->type,
            ]);

            return $question;
        });
    }

    protected function createQuestion(): Question
    {
        return Question::create([
            'user_id' => $this->draft->user_id,
            'type' => QuestionTypeEnum::from($this->draft->type),
            'difficulty' => QuestionDifficultyLevelEnum::from($this->draft->difficulty),
            'timer' => QuestionTimeEnum::from($this->draft->timer),
            'score' => QuestionScoreEnum::from($this->draft->score),
            'content' => $this->draft->content,
            'hint' => $this->draft->hint,
            'is_approved' => true,
        ]);
    }

    protected function createOptions(Question $question): void
    {
        $type = QuestionTypeEnum::from($this->draft->type);
        $optionsData = $this->draft->options ?? [];

        match ($type) {
            QuestionTypeEnum::MULTIPLE_CHOICE,
            QuestionTypeEnum::MULTIPLE_SELECTION => $this->createMultipleChoiceOptions($question, $optionsData),
            QuestionTypeEnum::TRUE_FALSE => $this->createTrueFalseOptions($question, $optionsData),
            QuestionTypeEnum::SHORT_ANSWER,
            QuestionTypeEnum::ARABIC_RESPONSE,
            QuestionTypeEnum::JAVANESE_RESPONSE => $this->createShortAnswerOptions($question, $optionsData),
            QuestionTypeEnum::ESSAY => $this->createEssayOption($question, $optionsData),
            QuestionTypeEnum::MATCHING => $this->createMatchingOptions($question, $optionsData),
            QuestionTypeEnum::SEQUENCE => $this->createSequenceOptions($question, $optionsData),
            QuestionTypeEnum::CATEGORIZATION => $this->createCategorizationOptions($question, $optionsData),
            QuestionTypeEnum::MATH_INPUT => $this->createMathInputOption($question, $optionsData),
            QuestionTypeEnum::ARRANGE_WORDS => $this->createArrangeWordsOption($question, $optionsData),
            default => $this->createGenericOptions($question, $optionsData),
        };
    }

    protected function createMultipleChoiceOptions(Question $question, array $optionsData): void
    {
        $options = collect($optionsData)->map(fn ($opt, $index) => [
            'option_key' => $opt['key'] ?? chr(65 + $index),
            'content' => $opt['content'] ?? '',
            'order' => $index,
            'is_correct' => $opt['is_correct'] ?? false,
        ])->toArray();

        Option::createMultipleChoiceOptions($question->id, $options);
    }

    protected function createTrueFalseOptions(Question $question, array $optionsData): void
    {
        $correctAnswer = collect($optionsData)
            ->firstWhere('is_correct', true);

        $isTrue = ($correctAnswer['option_key'] ?? 'T') === 'T';

        Option::createTrueFalseOptions($question->id, $isTrue);
    }

    protected function createShortAnswerOptions(Question $question, array $optionsData): void
    {
        $answers = collect($optionsData)
            ->where('is_correct', true)
            ->pluck('content')
            ->toArray();

        Option::createShortAnswerOptions($question->id, $answers);
    }

    protected function createEssayOption(Question $question, array $optionsData): void
    {
        $rubric = collect($optionsData)
            ->firstWhere('option_key', 'ESSAY');

        Option::createEssayOption(
            $question->id,
            $rubric['content'] ?? ''
        );
    }

    protected function createMatchingOptions(Question $question, array $optionsData): void
    {
        $leftOptions = collect($optionsData)
            ->filter(fn ($opt) => ($opt['metadata']['side'] ?? '') === 'left')
            ->sortBy(fn ($opt) => $opt['metadata']['pair_id'] ?? 0)
            ->values();

        $pairs = $leftOptions->map(fn ($left) => [
            'left' => $left['content'] ?? '',
            'right' => collect($optionsData)
                ->firstWhere('metadata.match_with', $left['option_key'])['content'] ?? '',
        ])->toArray();

        Option::createMatchingOptions($question->id, $pairs);
    }

    protected function createSequenceOptions(Question $question, array $optionsData): void
    {
        $items = collect($optionsData)
            ->sortBy(fn ($opt) => $opt['metadata']['correct_position'] ?? 0)
            ->pluck('content')
            ->toArray();

        Option::createOrderingOptions($question->id, $items);
    }

    protected function createCategorizationOptions(Question $question, array $optionsData): void
    {
        $groups = collect($optionsData)
            ->groupBy('metadata.group_title')
            ->map(fn ($items, $title) => [
                'title' => $title,
                'items' => $items->map(fn ($item) => ['content' => $item['content']])->toArray(),
            ])
            ->values()
            ->toArray();

        Option::createCategorizationOptions($question->id, $groups);
    }

    protected function createMathInputOption(Question $question, array $optionsData): void
    {
        $mathOption = collect($optionsData)->firstWhere('option_key', 'MATH');

        if ($mathOption) {
            Option::createMathInputOption(
                $question->id,
                $mathOption['metadata']['correct_answer'] ?? $mathOption['content'] ?? ''
            );
        }
    }

    protected function createArrangeWordsOption(Question $question, array $optionsData): void
    {
        $wordOption = collect($optionsData)->firstWhere('option_key', 'SENTENCE');

        if ($wordOption) {
            Option::createArrangeWordsOption(
                $question->id,
                $wordOption['content'] ?? '',
                $wordOption['metadata']['delimiter'] ?? ' ',
                $wordOption['metadata']['is_arabic'] ?? false,
                $wordOption['metadata']['shuffle_mode'] ?? 'phrase'
            );
        }
    }

    protected function createGenericOptions(Question $question, array $optionsData): void
    {
        foreach ($optionsData as $index => $optionData) {
            Option::create([
                'question_id' => $question->id,
                'option_key' => $optionData['option_key'] ?? $index + 1,
                'content' => $optionData['content'] ?? '',
                'order' => $index,
                'is_correct' => $optionData['is_correct'] ?? false,
                'metadata' => $optionData['metadata'] ?? null,
            ]);
        }
    }

    protected function updateDraftStatus(): void
    {
        $this->draft->update([
            'status' => QuestionDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $this->reviewedBy,
        ]);

        $this->draft->delete();
    }
}
