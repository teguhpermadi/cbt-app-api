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

final class CorrectExamQuestionLMStudioJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    protected string $model;

    protected ?float $jobStartedAt = null;

    public function __construct(
        public ExamResultDetail $examResultDetail,
        public ?string $triggeredBy = null,
        ?string $model = null,
        ?string $batchId = null
    ) {
        $this->model = $model ?? config('prism.lmstudio.model', 'gemma-3-4b');
        if ($batchId !== null) {
            $this->batchId = $batchId;
        }
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
            'description' => "Koreksi LM Studio ujian '{$exam->title}' siswa {$studentName} soal no {$question->question_number}",
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

            Log::info("LM Studio Correction (Empty Answer) - Student: {$studentName}, Question: ".strip_tags($question->content).", Score: 0/{$question->score_value}");

            return;
        }

        $keyAnswer = is_array($question->key_answer)
            ? json_encode($question->key_answer, JSON_UNESCAPED_UNICODE)
            : $question->key_answer;

        $maxScore = $question->score_value;

        try {
            $response = Prism::text()
                ->using('lmstudio', $this->model)
                ->withSystemPrompt('Kamu adalah asisten guru pakar. Selalu balas HANYA dengan JSON valid. JSON WAJIB memiliki field "score" (number) dan "notes" (string). Contoh: {"score": 8, "notes": "Jawaban sangat baik karena..."}')
                ->withPrompt("Koreksi jawaban siswa berikut dan BALAS HANYA dengan JSON (tanpa markdown, tanpa penjelasan lain):

Soal: {$question->content}
Kunci Jawaban: {$keyAnswer}
Jawaban Siswa: {$studentAnswer}
Skor Maksimal: {$maxScore}

JSON WAJIB format: {\"score\": <angka>, \"notes\": \"<catatan singkat dalam bahasa Indonesia>\"}")
                ->withClientOptions(['timeout' => 180])
                ->asText();

            Log::debug('LM Studio Raw Response', [
                'detail_id' => $detail->id,
                'text' => $response->text,
            ]);

            $aiScore = $this->extractScoreFromText($response->text, $maxScore);
            $aiNotes = $this->extractNotesFromText($response->text);

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

                    Log::info('LM Studio Correction Success', [
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
            Log::error("LM Studio Correction FAILED for Detail ID: {$detail->id}. Error: ".$e->getMessage());
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
                'total_score' => $totalEarnedScore,
                'score_percent' => $scorePercent,
                'is_passed' => $scorePercent >= ($session->exam->passing_score ?? 0),
                'result_type' => \App\Enums\ExamResultTypeEnum::OFFICIAL,
            ]
        );
    }

    protected function extractScoreFromText(string $text, float $maxScore): float
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
        if (is_array($decoded) && isset($decoded['score'])) {
            return (float) $decoded['score'];
        }

        if (is_array($decoded) && isset($decoded['correction']['score'])) {
            return (float) $decoded['correction']['score'];
        }

        if (preg_match('/"score"\s*:\s*([0-9]+(?:\.[0-9]+)?)/', $text, $matches)) {
            return (float) $matches[1];
        }

        if (preg_match('/score["\s:]+([0-9]+(?:\.[0-9]+)?)/i', $text, $matches)) {
            return (float) $matches[1];
        }

        Log::warning('Failed to extract score from LM Studio response', [
            'text' => $text,
        ]);

        return 0;
    }

    protected function extractNotesFromText(string $text): string
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
        if (is_array($decoded) && isset($decoded['notes'])) {
            return (string) $decoded['notes'];
        }

        if (is_array($decoded) && isset($decoded['correction']['notes'])) {
            return (string) $decoded['correction']['notes'];
        }

        if (preg_match('/"notes"\s*:\s*"([^"]+)"/', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/"feedback"\s*:\s*"([^"]+)"/', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/"catatan"\s*:\s*"([^"]+)"/', $text, $matches)) {
            return $matches[1];
        }

        return 'Koreksi AI selesai.';
    }

    protected function updateQuestionCorrectionProgress($examId, $questionId)
    {
        $correction = ExamQuestionCorrection::where('exam_id', $examId)
            ->where('exam_question_id', $questionId)
            ->first();

        if ($correction) {
            $correctedCount = ExamResultDetail::where('exam_question_id', $questionId)
                ->whereHas('examSession', function ($q) {
                    $q->where('is_finished', true);
                })
                ->whereNotNull('score_earned')
                ->count();

            $updateData = ['corrected_count' => $correctedCount];

            if ($correctedCount >= $correction->total_to_correct) {
                $updateData['status'] = CorrectionStatusEnum::COMPLETED;
            }

            $correction->update($updateData);
        }
    }
}
