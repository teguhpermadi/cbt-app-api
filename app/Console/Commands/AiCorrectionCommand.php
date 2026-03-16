<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AiCorrectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exam:ai-correct {exam_id?} {--provider=gemini : The AI provider to use (gemini or openrouter)} {--detail_id= : Specific ExamResultDetail ID to correct}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct exam results (Short Answer and Essay) using AI (Gemini or OpenRouter)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $examId = $this->argument('exam_id');
        $provider = $this->option('provider');
        $detailId = $this->option('detail_id');

        if (!in_array($provider, ['gemini', 'openrouter'])) {
            $this->error('Invalid provider. Supported providers are: gemini, openrouter');
            return;
        }

        if ($detailId) {
            $detail = \App\Models\ExamResultDetail::find($detailId);
            if (!$detail) {
                $this->error("ExamResultDetail with ID {$detailId} not found.");
                return;
            }

            $this->info("Dispatching correction for Detail ID: {$detailId} using {$provider}");
            if ($provider === 'openrouter') {
                \App\Jobs\CorrectExamQuestionOpenRouterJob::dispatch($detail);
            } else {
                \App\Jobs\CorrectExamQuestionJob::dispatch($detail);
            }
            $this->info('Job dispatched.');
            return;
        }

        $examId = $examId ?: $this->ask('Please enter the Exam ID');

        if (!$examId) {
            $this->error('Exam ID is required.');
            return;
        }

        $exam = \App\Models\Exam::find($examId);

        if (!$exam) {
            $this->error('Exam not found.');
            return;
        }

        $this->info("Starting AI correction for Exam: {$exam->title} using {$provider}");

        $resultDetails = \App\Models\ExamResultDetail::whereHas('examSession', function ($query) use ($examId) {
            $query->where('exam_id', $examId);
        })->whereHas('examQuestion', function ($query) {
            $query->whereIn('question_type', [
                \App\Enums\QuestionTypeEnum::SHORT_ANSWER->value,
                \App\Enums\QuestionTypeEnum::ESSAY->value,
                \App\Enums\QuestionTypeEnum::ARABIC_RESPONSE->value,
                \App\Enums\QuestionTypeEnum::JAVANESE_RESPONSE->value,
            ]);
        })->get();

        if ($resultDetails->isEmpty()) {
            $this->warn('No short answer or essay questions found for this exam.');
            return;
        }

        $this->info("Found {$resultDetails->count()} answers to correct.");

        $bar = $this->output->createProgressBar($resultDetails->count());
        $bar->start();

        foreach ($resultDetails as $detail) {
            if ($provider === 'openrouter') {
                \App\Jobs\CorrectExamQuestionOpenRouterJob::dispatch($detail);
            } else {
                \App\Jobs\CorrectExamQuestionJob::dispatch($detail);
            }
            usleep(500000); // 0.5 seconds delay to prevent immediate rate limit
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Correction jobs ({$provider}) have been dispatched to the queue.");
    }
}
