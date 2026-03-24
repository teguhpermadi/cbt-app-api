<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionTypeEnum;
use App\Jobs\GenerateQuestionJob;
use App\Models\Curriculum;
use App\Models\Taxonomy;
use Illuminate\Console\Command;
use Throwable;

final class GenerateQuestionsCommand extends Command
{
    protected $signature = 'questions:generate
        {--prompt= : Custom prompt for AI (skips curriculum context)}
        {--curriculum= : Curriculum ID}
        {--subject= : Subject code (e.g., MAT, IPA)}
        {--type=multiple_choice : Question type}
        {--difficulty=mudah : Difficulty level (mudah, sedang, sulit)}
        {--count=5 : Number of questions to generate}
        {--taxonomy= : Taxonomy type (anderson_krathwohl, bloom, solo)}
        {--category= : Taxonomy category code (e.g., CP2, K, R)}
        {--material= : Custom material from user}
        {--model=openai/gpt-4o-mini : OpenRouter model}';

    protected $description = 'Generate questions using AI';

    public function handle(): int
    {
        $customPrompt = $this->option('prompt');
        $curriculumId = $this->option('curriculum');
        $subjectCode = $this->option('subject');
        $typeValue = $this->option('type');
        $difficultyValue = $this->option('difficulty');
        $count = (int) $this->option('count');
        $taxonomyType = $this->option('taxonomy');
        $categoryCode = $this->option('category');
        $customMaterial = $this->option('material');
        $model = $this->option('model');

        $curriculum = null;
        if ($curriculumId) {
            $curriculum = Curriculum::find($curriculumId);
            if (! $curriculum) {
                $this->error("Curriculum with ID {$curriculumId} not found");

                return self::FAILURE;
            }
        }

        $type = QuestionTypeEnum::tryFrom($typeValue);
        if (! $type) {
            $this->error("Invalid question type: {$typeValue}");
            $this->info('Available types: '.implode(', ', array_column(QuestionTypeEnum::cases(), 'value')));

            return self::FAILURE;
        }

        $difficulty = QuestionDifficultyLevelEnum::tryFrom($difficultyValue);
        if (! $difficulty) {
            $this->error("Invalid difficulty: {$difficultyValue}");
            $this->info('Available difficulties: mudah, sedang, sulit');

            return self::FAILURE;
        }

        $taxonomy = null;
        if ($taxonomyType && $categoryCode) {
            $taxonomy = Taxonomy::byType($taxonomyType)
                ->byCode($categoryCode)
                ->first();

            if (! $taxonomy) {
                $this->warn("Taxonomy not found: {$taxonomyType} - {$categoryCode}");
            }
        }

        if ($customPrompt) {
            $this->info("Generating {$count} questions with custom prompt...");
            $this->info("Type: {$type->label()}");
            $this->info("Difficulty: {$difficulty->getLabel()}");
            $this->info('Prompt: '.mb_substr($customPrompt, 0, 50).(mb_strlen($customPrompt) > 50 ? '...' : ''));
        } else {
            if (! $curriculum) {
                $this->error('Curriculum ID is required when not using custom prompt. Use --curriculum=');

                return self::FAILURE;
            }

            if (! $subjectCode) {
                $this->error('Subject code is required when not using custom prompt. Use --subject=');

                return self::FAILURE;
            }

            $this->info("Generating {$count} questions...");
            $this->info("Curriculum: {$curriculum->name}");
            $this->info("Subject: {$subjectCode}");
            $this->info("Type: {$type->label()}");
            $this->info("Difficulty: {$difficulty->getLabel()}");

            if ($taxonomy) {
                $this->info("Taxonomy: {$taxonomy->name} ({$taxonomy->code})");
            }

            if ($customMaterial) {
                $this->info("Custom Material: {$customMaterial}");
            }
        }

        try {
            $job = new GenerateQuestionJob(
                curriculum: $curriculum,
                subjectCode: $subjectCode,
                type: $type,
                difficulty: $difficulty,
                count: $count,
                customMaterial: $customMaterial,
                taxonomy: $taxonomy,
                userId: null,
                model: $model,
                customPrompt: $customPrompt
            );

            $contextService = app(\App\Services\AI\QuestionGeneratorContextService::class);
            $draftIds = $job->handle($contextService);

            if (empty($draftIds)) {
                $this->error('No questions were generated');

                return self::FAILURE;
            }

            $this->info("\nSuccessfully generated ".count($draftIds).' questions!');
            $this->info('Draft IDs: '.implode(', ', $draftIds));

            $this->info("\nTo review and approve:");
            $this->info('1. List pending drafts: QuestionDraft::pending()->get()');
            $this->info('2. Approve: ApproveQuestionDraftJob::dispatchSync($draft)');
            $this->info("3. Reject: RejectQuestionDraftJob::dispatchSync(\$draft, 'reason')");

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Failed to generate questions: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
