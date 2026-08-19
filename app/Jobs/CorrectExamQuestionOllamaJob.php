<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CorrectionStatusEnum;
use App\Models\AiCorrectionStat;
use App\Models\ExamQuestionCorrection;
use App\Models\ExamResult;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Facades\Prism;
use romanzipp\QueueMonitor\Traits\IsMonitored;
use Throwable;

final class CorrectExamQuestionOllamaJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    protected string $model;

    protected ?float $jobStartedAt = null;

    public function __construct(
        public ExamResultDetail $examResultDetail,
        public ?string $triggeredBy = null,
        ?string $model = null
    ) {
        $this->model = $model ?? config('prism.providers.ollama.model', 'phi4-mini:latest');
    }

    public function handle(): void
    {
        $this->jobStartedAt = microtime(true);

        $detail = $this->examResultDetail->load(['examQuestion', 'examSession.exam', 'examSession.user']);
        $question = $detail->examQuestion;
        $session = $detail->examSession;
        $exam = $session->exam;
        $studentName = $session->user->name;

        $queueDataPayload = [
            'description' => "Koreksi Ollama ujian '{$exam->title}' siswa {$studentName} soal no {$question->question_number}",
            'model' => $this->model,
        ];

        if ($this->triggeredBy) {
            $queueDataPayload['triggered_by'] = $this->triggeredBy;
        }

        $this->queueData($queueDataPayload);

        $studentAnswer = is_array($detail->student_answer)
            ? json_encode($detail->student_answer, JSON_UNESCAPED_UNICODE)
            : $detail->student_answer;

        if (empty($studentAnswer)) {
            $detail->update([
                'score_earned' => 0,
                'is_correct' => false,
                'correction_notes' => 'Siswa tidak menjawab.',
            ]);
            $this->updateSessionTotals($session);

            Log::info("Ollama Correction (Empty Answer) - Student: {$studentName}, Question: ".strip_tags($question->content).", Score: 0/{$question->score_value}");

            return;
        }

        $keyAnswer = is_array($question->key_answer)
            ? json_encode($question->key_answer, JSON_UNESCAPED_UNICODE)
            : $question->key_answer;

        $maxScore = $question->score_value;

        try {
            $response = Prism::text()
                ->using('ollama', $this->model)
                ->withSystemPrompt('Kamu adalah asisten guru pakar dan analis integritas akademik. Selalu balas HANYA dengan JSON valid. JSON WAJIB memiliki field "score" (number), "notes" (string), "cheat_probability" (number, 0-100), dan "ai_analysis" (string). Contoh: {"score": 8, "notes": "Jawaban baik...", "cheat_probability": 10, "ai_analysis": "Jawaban terlihat natural."}')
                ->withPrompt("Koreksi jawaban siswa berikut dan analisis apakah ada kemungkinan jawaban ini dibuat oleh AI (seperti ChatGPT). BALAS HANYA dengan JSON:

Soal: {$question->content}
Kunci Jawaban: {$keyAnswer}
Jawaban Siswa: {$studentAnswer}
Skor Maksimal: {$maxScore}

Metadata Pengerjaan:
- Jumlah Paste: " . ($detail->metadata['paste_count'] ?? 0) . "
- Jumlah Pindah Tab: " . ($detail->metadata['tab_switches'] ?? 0) . "

JSON WAJIB format: {
    \"score\": <angka>, 
    \"notes\": \"<catatan koreksi>\",
    \"cheat_probability\": <angka 0-100, probabilitas jawaban dari AI>,
    \"ai_analysis\": \"<analisis singkat mengapa jawaban ini dicurigai dari AI atau tidak>\"
}")
                ->withClientOptions(['timeout' => 180])
                ->asText();

            Log::debug('Ollama Raw Response', [
                'detail_id' => $detail->id,
                'text' => $response->text,
            ]);

            $decoded = json_decode($this->cleanJsonResponse($response->text), true);
            $aiScore = $decoded['score'] ?? 0;
            $aiNotes = $decoded['notes'] ?? 'Koreksi AI selesai.';
            $cheatProb = $decoded['cheat_probability'] ?? 0;
            $aiAnalysis = $decoded['ai_analysis'] ?? '';

            $metadata = $detail->metadata ?? [];
            $metadata['ai_cheat_probability'] = $cheatProb;
            $metadata['ai_integrity_analysis'] = $aiAnalysis;
            $detail->metadata = $metadata;
            $detail->save();

            if ($aiScore > $maxScore) {
                $aiScore = $maxScore;
            }
            if ($aiScore < 0) {
                $aiScore = 0;
            }

            DB::transaction(function () use ($detail, $aiScore, $aiNotes, $session, $studentName, $question) {
                try {
                    Log::debug("Transaction Start - Detail ID: {$detail->id}, Session ID: {$session->id}");

                    $detail->update([
                        'score_earned' => $aiScore,
                        'is_correct' => ($aiScore > 0),
                        'correction_notes' => $aiNotes,
                    ]);

                    $this->updateQuestionCorrectionProgress($question->exam_id, $question->id);
                    $this->updateSessionTotals($session);

                    Log::info('Ollama Correction Success', [
                        'student_name' => $studentName,
                        'model' => $this->model,
                        'question' => strip_tags($question->content),
                        'earned_score' => $aiScore,
                    ]);
                } catch (Throwable $transactionError) {
                    Log::error("Transaction Error during AI Correction for Detail ID: {$detail->id}. Class: ".get_class($transactionError).'. Error: '.$transactionError->getMessage());
                    throw $transactionError;
                }
            });

            Log::debug("Transaction committed for Detail ID: {$detail->id}");
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                $this->release(60);

                return;
            }

            Log::error("Ollama Correction FAILED for Detail ID: {$detail->id}. Error: ".$e->getMessage());
            throw $e;
        }
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
    }

    protected function updateSessionTotals(ExamSession $session)
    {
        $totalEarnedScore = ExamResultDetail::where('exam_session_id', $session->id)->sum('score_earned');

        $totalMaxScore = ExamResultDetail::join('exam_questions', 'exam_result_details.exam_question_id', '=', 'exam_questions.id')
            ->where('exam_result_details.exam_session_id', $session->id)
            ->sum('exam_questions.score_value');

        Log::debug("Recalculated Totals - Session ID: {$session->id}, Total Earned: {$totalEarnedScore}, Total Max: {$totalMaxScore}");

        $session->update([
            'total_score' => $totalEarnedScore,
            'total_max_score' => $totalMaxScore,
            'is_corrected' => true,
        ]);

        $scorePercent = $totalMaxScore > 0 ? round(($totalEarnedScore / $totalMaxScore) * 100, 1) : 0;

        ExamResult::updateOrCreate(
            [
                'exam_id' => $session->exam_id,
                'user_id' => $session->user_id,
            ],
            [
                'exam_session_id' => $session->id,
                'score_percent' => $scorePercent,
                'total_score' => $totalEarnedScore,
                'total_max_score' => $totalMaxScore,
            ]
        );
    }

    protected function updateQuestionCorrectionProgress($examId, $questionId)
    {
        $correction = ExamQuestionCorrection::where('exam_id', $examId)
            ->where('exam_question_id', $questionId)
            ->first();

        if ($correction) {
            $correctedCount = ExamResultDetail::where('exam_question_id', $questionId)
                ->where('is_correct', true)
                ->count();

            $updateData = ['corrected_count' => $correctedCount];

            if ($correctedCount >= $correction->total_to_correct) {
                $updateData['status'] = CorrectionStatusEnum::COMPLETED;
            }

            $correction->update($updateData);
        }
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
}
