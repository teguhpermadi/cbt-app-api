<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiCorrectionStat;
use App\Models\Question;
use App\Models\QuestionBank;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use romanzipp\QueueMonitor\Traits\IsMonitored;
use Throwable;

final class GenerateQuestionTagsJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    protected string $model;

    protected ?float $jobStartedAt = null;

    public function __construct(
        public QuestionBank $questionBank,
        public array $scopes,
        public bool $processUntaggedOnly = false,
        ?string $model = null
    ) {
        $this->model = $model ?? config('prism.lmstudio.model', 'gemma-3-4b');
    }

    public function handle(): void
    {
        $this->jobStartedAt = microtime(true);

        $this->queueData([
            'description' => "Generate tags untuk QuestionBank '{$this->questionBank->name}'",
            'model' => $this->model,
            'scopes' => $this->scopes,
            'process_untagged_only' => $this->processUntaggedOnly,
        ]);

        $scopesList = implode(', ', $this->scopes);

        $query = $this->questionBank->questions();

        if ($this->processUntaggedOnly) {
            $query->whereDoesntHave('tags', function ($q) {
                $q->where('taggable_type', Question::class);
            });
        }

        $questions = $query->get();

        if ($questions->isEmpty()) {
            Log::info('GenerateQuestionTagsJob: No questions to process', [
                'question_bank_id' => $this->questionBank->id,
                'question_bank_name' => $this->questionBank->name,
                'process_untagged_only' => $this->processUntaggedOnly,
            ]);

            return;
        }

        Log::info('GenerateQuestionTagsJob: Starting tag generation', [
            'question_bank_id' => $this->questionBank->id,
            'total_questions' => $questions->count(),
            'scopes' => $this->scopes,
        ]);

        $successCount = 0;
        $failedCount = 0;

        foreach ($questions as $question) {
            try {
                $this->processQuestionTags($question, $scopesList);
                $successCount++;

                Log::debug('GenerateQuestionTagsJob: Question tagged', [
                    'question_id' => $question->id,
                    'content_preview' => mb_substr(strip_tags($question->content), 0, 50),
                ]);

            } catch (Throwable $e) {
                $failedCount++;

                Log::error('GenerateQuestionTagsJob: Failed to tag question', [
                    'question_id' => $question->id,
                    'error' => $e->getMessage(),
                ]);

                if (str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                    $this->release(60);

                    return;
                }
            }
        }

        Log::info('GenerateQuestionTagsJob: Completed', [
            'question_bank_id' => $this->questionBank->id,
            'total_questions' => $questions->count(),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);
    }

    protected function processQuestionTags(Question $question, string $scopesList): void
    {
        $response = Prism::text()
            ->using('lmstudio', $this->model)
            ->withSystemPrompt('Kamu adalah asisten guru pakar dalam membuat tag soal. Selalu balas HANYA dengan JSON array string. Buat HANYA 3 tag yang paling relevan dengan pertanyaan. Tag harus dalam bahasa Indonesia, singkat (1-3 kata), dan menggambarkan topik atau konsep utama soal.')
            ->withPrompt("Buat 3 tag untuk soal berikut dengan mempertimbangkan ruang lingkup yang diberikan.

Soal: {$question->content}
Ruang Lingkup: {$scopesList}

JSON WAJIB format (HANYA JSON, tanpa teks lain):
[\"tag1\", \"tag2\", \"tag3\"]

Catatan:
- Tag harus relevan dengan konten soal
- Tag harus mempertimbangkan ruang lingkup yang diberikan
- Tag dalam bahasa Indonesia, singkat (1-3 kata)
- Maksimal 3 tag saja")
            ->withClientOptions(['timeout' => 180])
            ->asText();

        $tags = $this->extractTagsFromResponse($response->text);

        if (! empty($tags)) {
            $question->syncTagsWithType($tags, (string) $this->questionBank->id);
        }
    }

    protected function extractTagsFromResponse(string $text): array
    {
        $text = trim($text);

        if (str_starts_with($text, '```json')) {
            $text = mb_substr($text, 7);
        }
        if (str_starts_with($text, '```')) {
            $text = mb_substr($text, 3);
        }
        if (str_ends_with(trim($text), '```')) {
            $text = mb_substr(trim($text), 0, -3);
        }
        $text = trim($text);

        $decoded = json_decode($text, true);

        if (is_array($decoded)) {
            $tags = array_filter($decoded, 'is_string');

            return array_slice(array_values($tags), 0, 3);
        }

        if (preg_match_all('/"([^"]+)"/', $text, $matches)) {
            $tags = array_filter($matches[1], 'is_string');

            return array_slice(array_values($tags), 0, 3);
        }

        Log::warning('GenerateQuestionTagsJob: Failed to extract tags from response', [
            'text' => mb_substr($text, 0, 200),
        ]);

        return [];
    }

    public function completed(): void
    {
        if ($this->jobStartedAt && $this->batchId) {
            $executionTime = microtime(true) - $this->jobStartedAt;
            AiCorrectionStat::recordJobCompletion($this->batchId, $executionTime);
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->batchId) {
            AiCorrectionStat::recordJobFailure($this->batchId);
        }

        Log::error('GenerateQuestionTagsJob: Job failed', [
            'question_bank_id' => $this->questionBank->id,
            'error' => $exception->getMessage(),
        ]);
    }
}