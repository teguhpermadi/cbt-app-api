<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateQuestionTagsJob;
use App\Models\QuestionBank;
use Illuminate\Console\Command;
use Throwable;

final class GenerateQuestionTagsCommand extends Command
{
    protected $signature = 'questions:generate-tags
        {--question-bank= : QuestionBank ID (required)}
        {--scopes= : Comma-separated scopes (required)}
        {--untagged-only : Process only questions without tags}
        {--model= : AI model (default from config)}';

    protected $description = 'Generate tags for questions in a QuestionBank using AI';

    public function handle(): int
    {
        $questionBankId = $this->option('question-bank');
        $scopesOption = $this->option('scopes');
        $processUntaggedOnly = $this->option('untagged-only');
        $model = $this->option('model');

        if (! $questionBankId) {
            $questionBankId = $this->ask('Masukkan QuestionBank ID:');
        }

        if (! $scopesOption) {
            $scopesOption = $this->ask('Masukkan Ruang Lingkup (comma-separated, contoh: aljabar, persamaan, fungsi):');
        }

        $questionBank = QuestionBank::find($questionBankId);

        if (! $questionBank) {
            $this->error("QuestionBank dengan ID '{$questionBankId}' tidak ditemukan.");

            return self::FAILURE;
        }

        $scopes = array_map('trim', explode(',', $scopesOption));
        $scopes = array_filter($scopes);

        if (empty($scopes)) {
            $this->error('Ruang lingkup tidak boleh kosong.');

            return self::FAILURE;
        }

        if (! $processUntaggedOnly && ! $this->confirm('Proses semua pertanyaan (termasuk yang sudah memiliki tags)?', false)) {
            $this->info('Dibatalkan.');

            return self::FAILURE;
        }

        $questionsQuery = $questionBank->questions();

        if ($processUntaggedOnly) {
            $questionsQuery->whereDoesntHave('tags');
        }

        $totalQuestions = $questionsQuery->count();

        if ($totalQuestions === 0) {
            $this->warn('Tidak ada pertanyaan yang perlu diproses.');

            return self::FAILURE;
        }

        $this->info('========================================');
        $this->info('          RINGKASAN PROSES              ');
        $this->info('========================================');
        $this->line("QuestionBank : {$questionBank->name}");
        $this->line("ID          : {$questionBank->id}");
        $this->line("Scopes      : ".implode(', ', $scopes));
        $this->line("Model AI    : ".($model ?? config('prism.lmstudio.model', 'gemma-3-4b')));
        $this->line("Filter      : ".($processUntaggedOnly ? 'Hanya pertanyaan tanpa tags' : 'Semua pertanyaan'));
        $this->line("Total       : {$totalQuestions} pertanyaan");
        $this->info('========================================');

        if (! $this->confirm('Lanjutkan proses?', true)) {
            $this->info('Dibatalkan.');

            return self::FAILURE;
        }

        try {
            $job = new GenerateQuestionTagsJob(
                questionBank: $questionBank,
                scopes: $scopes,
                processUntaggedOnly: $processUntaggedOnly,
                model: $model
            );

            $job->handle();

            $this->newLine();
            $this->info('Proses tagging selesai!');

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->error('Gagal menjalankan proses: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}