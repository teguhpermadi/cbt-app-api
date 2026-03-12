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
    protected $signature = 'exam:ai-correct {exam_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Correct exam results (Short Answer and Essay) using Gemini AI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $examId = $this->argument('exam_id') ?: $this->ask('Please enter the Exam ID');

        if (!$examId) {
            $this->error('Exam ID is required.');
            return;
        }

        $exam = \App\Models\Exam::find($examId);

        if (!$exam) {
            $this->error('Exam not found.');
            return;
        }

        $this->info("Starting AI correction for Exam: {$exam->title}");

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
            \App\Jobs\CorrectExamQuestionJob::dispatch($detail);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Correction jobs have been dispatched to the queue.');
    }
}
