<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\QuestionTypeEnum;
use App\Jobs\CorrectExamQuestionOllamaJob;
use App\Models\ExamResultDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Prism\Prism\Facades\Prism;

final class TestOllamaEssayCorrectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     * php artisan exam:test-ollama-essay --exam_id=12 --model=phi4-mini --url=https://ollama.cbtmiarridlo.com/
     * php artisan exam:test-ollama-essay --detail_id=458
     */
    protected $signature = 'exam:test-ollama-essay {detail_id?} {--exam_id=} {--model=phi4-mini:latest : Nama model Ollama yang dipakai} {--url=https://ollama.cbtmiarridlo.com/ : Base URL Ollama} {--all : Jalankan semua jawaban essay yang cocok} {--debug : Tampilkan raw response AI untuk debugging} {--dry-run : Tidak menyimpan hasil koreksi ke database}';

    /**
     * The console command description.
     */
    protected $description = 'Testing koreksi essai dengan provider Ollama ke endpoint publik di https://ollama.cbtmiarridlo.com/';

    public function handle(): int
    {
        $model = $this->option('model');
        $url = rtrim((string) $this->option('url'), '/');

        config()->set('prism.providers.ollama.url', $url);
        config()->set('prism.providers.ollama.model', $model);

        $this->info('Ollama endpoint: '.$url);
        $this->info('Model: '.$model);

        $details = $this->resolveDetails();

        if ($details->isEmpty()) {
            $this->error('Tidak ada detail jawaban yang bisa diproses.');

            return self::FAILURE;
        }

        $this->warn('Memulai koreksi essai via Ollama...');

        $rows = [];
        foreach ($details as $detail) {
            $this->line('');

            $studentName = $detail->examSession?->user?->name;
            $studentName = $studentName ?? '-';

            $this->info("Detail ID: {$detail->id} | Student: {$studentName}");

            if ($this->option('debug')) {
                $response = $this->debugResponseFromAi($detail, $model);
                $this->line('RAW RESPONSE:');
                $this->line($response['raw']);
                $this->line('PARSED JSON:');
                $this->printJson($response['decoded']);
            }

            if (! $this->option('dry-run')) {
                $job = new CorrectExamQuestionOllamaJob($detail, 'manual-test-command', $model);
                $job->handle();
                $detail->refresh();
            }

            $rows[] = [
                $detail->id,
                $studentName,
                (string) ($detail->score_earned ?? 0),
                $detail->is_correct ? 'Ya' : 'Tidak',
                $detail->correction_notes ?? '-',
            ];
        }

        $this->info('Koreksi selesai.');
        $this->table(
            ['Detail ID', 'Student', 'Score', 'Benar', 'Catatan'],
            $rows
        );

        return self::SUCCESS;
    }

    protected function resolveDetails(): Collection
    {
        $detailId = $this->argument('detail_id');

        if ($detailId) {
            $detail = ExamResultDetail::with(['examSession.user', 'examQuestion'])->find($detailId);

            return $detail ? collect([$detail]) : collect();
        }

        $examId = $this->option('exam_id');

        if (! $examId) {
            $examId = $this->ask('Masukkan Exam ID untuk testing');
        }

        if (! $examId) {
            return collect();
        }

        $query = ExamResultDetail::query()
            ->with(['examSession.user', 'examQuestion'])
            ->whereHas('examSession', fn ($q) => $q->where('exam_id', $examId))
            ->whereHas('examQuestion', fn ($q) => $q->whereIn('question_type', [
                QuestionTypeEnum::ESSAY->value,
                QuestionTypeEnum::ARABIC_RESPONSE->value,
                QuestionTypeEnum::JAVANESE_RESPONSE->value,
            ]));

        if (! $this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('is_correct')
                    ->orWhere('is_correct', false);
            });
        }

        return $query->orderBy('id')->get();
    }

    protected function debugResponseFromAi(ExamResultDetail $detail, string $model): array
    {
        $question = $detail->examQuestion;
        $studentAnswer = is_array($detail->student_answer)
            ? json_encode($detail->student_answer, JSON_UNESCAPED_UNICODE)
            : $detail->student_answer;

        $keyAnswer = is_array($question->key_answer)
            ? json_encode($question->key_answer, JSON_UNESCAPED_UNICODE)
            : $question->key_answer;

        $response = Prism::text()
            ->using('ollama', $model)
            ->withSystemPrompt('Kamu adalah asisten guru pakar dan analis integritas akademik. Selalu balas HANYA dengan JSON valid. JSON WAJIB memiliki field "score" (number), "notes" (string), "cheat_probability" (number, 0-100), dan "ai_analysis" (string).')
            ->withPrompt("Koreksi jawaban siswa berikut dan analisis apakah ada kemungkinan jawaban ini dibuat oleh AI. BALAS HANYA dengan JSON valid.

Soal: {$question->content}
Kunci Jawaban: {$keyAnswer}
Jawaban Siswa: {$studentAnswer}
Skor Maksimal: {$question->score_value}

JSON WAJIB format: {
    \"score\": <angka>, 
    \"notes\": \"<catatan koreksi>\",
    \"cheat_probability\": <angka 0-100>,
    \"ai_analysis\": \"<analisis singkat>\"
}")
            ->withClientOptions(['timeout' => 180])
            ->asText();

        $decoded = json_decode($this->cleanJsonResponse($response->text), true);

        return [
            'raw' => $response->text,
            'decoded' => $decoded,
        ];
    }

    protected function cleanJsonResponse(string $text): string
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

        return trim($text);
    }

    protected function printJson(mixed $data): void
    {
        if ($data === null) {
            $this->line('null');

            return;
        }

        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
