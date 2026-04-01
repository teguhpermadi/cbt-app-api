<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CorrectionStatusEnum;
use App\Enums\QuestionTypeEnum;
use App\Events\AiCorrectionFinished;
use App\Models\ExamQuestionCorrection;
use App\Models\User;
use App\Notifications\AiCorrectionFinishedNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

final class AiCorrectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:ai-correct {exam_id?} {--provider=gemini : The AI provider to use (gemini, openrouter, or lmstudio)} {--model=gemma-3-4b : Model name for lmstudio provider} {--detail_id= : Specific ExamResultDetail ID to correct} {--user_id= : User ID to notify when finished}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct exam results (Short Answer and Essay) using AI (Gemini, OpenRouter, or LM Studio)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $examId = $this->argument('exam_id');
        $provider = $this->option('provider');
        $detailId = $this->option('detail_id');

        if (! in_array($provider, ['gemini', 'openrouter', 'lmstudio'])) {
            $this->error('Invalid provider. Supported providers are: gemini, openrouter, lmstudio');

            return;
        }

        if ($detailId) {
            $detail = \App\Models\ExamResultDetail::find($detailId);
            if (! $detail) {
                $this->error("ExamResultDetail with ID {$detailId} not found.");

                return;
            }

            $this->info("Dispatching correction for Detail ID: {$detailId} using {$provider}");
            if ($provider === 'openrouter') {
                \App\Jobs\CorrectExamQuestionOpenRouterJob::dispatch($detail);
            } elseif ($provider === 'lmstudio') {
                \App\Jobs\CorrectExamQuestionLMStudioJob::dispatch($detail, null, $this->option('model'));
            } else {
                \App\Jobs\CorrectExamQuestionJob::dispatch($detail);
            }
            $this->info('Job dispatched.');

            return;
        }

        $examId = $examId ?: $this->ask('Please enter the Exam ID');

        if (! $examId) {
            $this->error('Exam ID is required.');

            return;
        }

        $exam = \App\Models\Exam::find($examId);

        if (! $exam) {
            $this->error('Exam not found.');

            return;
        }

        $this->info("Starting AI correction for Exam: {$exam->title} using {$provider}");

        $resultDetails = \App\Models\ExamResultDetail::whereHas('examSession', function ($query) use ($examId) {
            $query->where('exam_id', $examId);
        })->whereHas('examQuestion', function ($query) {
            $query->whereIn('question_type', [
                // \App\Enums\QuestionTypeEnum::SHORT_ANSWER->value,
                QuestionTypeEnum::ESSAY->value,
                QuestionTypeEnum::ARABIC_RESPONSE->value,
                QuestionTypeEnum::JAVANESE_RESPONSE->value,
            ]);
        })->get();

        if ($resultDetails->isEmpty()) {
            $this->warn('No short answer or essay questions found for this exam.');

            return;
        }

        $this->info("Found {$resultDetails->count()} answers to correct.");

        $bar = $this->output->createProgressBar($resultDetails->count());
        $bar->start();

        // Group by question to initialize tracking
        $questionsToTrack = $resultDetails->groupBy('exam_question_id');
        foreach ($questionsToTrack as $questionId => $details) {
            ExamQuestionCorrection::updateOrCreate(
                ['exam_id' => $exam->id, 'exam_question_id' => $questionId],
                [
                    'status' => CorrectionStatusEnum::PROCESSING,
                    'total_to_correct' => $details->count(),
                    'corrected_count' => 0,
                ]
            );
        }

        $bar->finish();
        $this->newLine();

        $userId = $this->option('user_id');
        $jobs = [];

        foreach ($resultDetails as $detail) {
            if ($provider === 'openrouter') {
                $jobs[] = new \App\Jobs\CorrectExamQuestionOpenRouterJob($detail);
            } elseif ($provider === 'lmstudio') {
                $jobs[] = new \App\Jobs\CorrectExamQuestionLMStudioJob($detail, null, $this->option('model'));
            } else {
                $jobs[] = new \App\Jobs\CorrectExamQuestionJob($detail);
            }
        }

        $batch = Bus::batch($jobs)
            ->then(function (\Illuminate\Bus\Batch $batch) use ($exam, $userId) {
                if ($userId) {
                    $user = User::find($userId);
                    if ($user) {
                        $message = "Koreksi AI untuk ujian '{$exam->title}' telah selesai.";

                        // Database Notification
                        $user->notify(new AiCorrectionFinishedNotification($exam->id, $exam->title, $message));

                        // Real-time Event
                        event(new AiCorrectionFinished($exam->id, $userId, $message));
                    }
                }
            })
            ->name("AI Correction: {$exam->title}")
            ->dispatch();

        $this->info("Correction jobs ({$provider}) have been batched and dispatched. Batch ID: {$batch->id}");
    }
}
