<?php

namespace App\Jobs;

use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use App\Models\ExamResult;
use App\Models\ExamQuestionCorrection;
use App\Enums\CorrectionStatusEnum;
use App\Events\AiScoreUpdated;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Enums\StructuredMode;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CorrectExamQuestionOpenRouterJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ExamResultDetail $examResultDetail
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $detail = $this->examResultDetail->load(['examQuestion', 'examSession.exam', 'examSession.user']);
        $question = $detail->examQuestion;
        $session = $detail->examSession;
        $exam = $session->exam;
        $studentName = $session->user->name;

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

            Log::info("OpenRouter Correction (Empty Answer) - Student: {$studentName}, Question: " . strip_tags($question->content) . ", Score: 0/{$question->score_value}");

            return;
        }

        $keyAnswer = is_array($question->key_answer)
            ? json_encode($question->key_answer, JSON_UNESCAPED_UNICODE)
            : $question->key_answer;

        $maxScore = $question->score_value;

        try {
            // Using openrouter/free as requested by user via OpenRouter
            $response = Prism::structured()
                ->using(Provider::OpenRouter, 'openrouter/free')
                ->withSystemPrompt("Kamu adalah asisten guru pakar. Kamu HARUS merespon HANYA dalam format JSON yang valid sesuai skema yang diberikan.")
                ->withPrompt("Koreksi jawaban siswa berikut:
                
                Soal: {$question->content}
                Kunci Jawaban: {$keyAnswer}
                Jawaban Siswa: {$studentAnswer}
                Skor Maksimal: {$maxScore}
                
                Berikan skor antara 0 sampai {$maxScore}. Jangan melebihi skor maksimal.
                Berikan catatan singkat (feedback) mengapa skor tersebut diberikan.")
                ->withSchema(new ObjectSchema(
                    'correction',
                    'The correction result',
                    [
                        new NumberSchema('score', 'The score earned by the student (0 to ' . $maxScore . ')'),
                        new StringSchema('notes', 'Brief feedback or explanation for the score'),
                    ],
                    ['score', 'notes']
                ))
                ->withClientOptions(['timeout' => 120])
                ->asStructured();

            $aiScore = (float) ($response->structured['score'] ?? 0);
            $aiNotes = $response->structured['notes'] ?? 'Koreksi AI selesai.';

            // Ensure score doesn't exceed max score
            if ($aiScore > $maxScore) {
                $aiScore = $maxScore;
            }
            if ($aiScore < 0) {
                $aiScore = 0;
            }

            DB::transaction(function () use ($detail, $aiScore, $aiNotes, $maxScore, $session, $studentName, $question, $studentAnswer) {
                try {
                    Log::debug("Transaction Start - Detail ID: {$detail->id}, Session ID: {$session->id}");

                    $detail->update([
                        'score_earned' => $aiScore,
                        'is_correct' => ($aiScore > 0),
                        'correction_notes' => $aiNotes,
                    ]);

                    $this->updateQuestionCorrectionProgress($question->exam_id, $question->id);
                    $this->updateSessionTotals($session);

                    Log::info("OpenRouter Correction Success", [
                        'student_name' => $studentName,
                        'model' => 'openrouter/free',
                        'question' => strip_tags($question->content),
                        'earned_score' => $aiScore,
                    ]);

                } catch (\Throwable $transactionError) {
                    Log::error("Transaction Error during AI Correction for Detail ID: {$detail->id}. Class: " . get_class($transactionError) . ". Error: " . $transactionError->getMessage());
                    throw $transactionError;
                }
            });

            Log::debug("Transaction committed for Detail ID: {$detail->id}");

            // Dispatch event for real-time frontend update OUTSIDE the transaction
            try {
                if (!empty($session->exam_id) && !empty($detail->id)) {
                    AiScoreUpdated::dispatch((string)$session->exam_id, (string)$detail->id);
                    Log::debug("AiScoreUpdated dispatched.");
                } else {
                    Log::warning("Skipping AiScoreUpdated dispatch due to missing IDs.");
                }
            } catch (\Throwable $broadcastError) {
                Log::warning("AiScoreUpdated broadcast failed but score was saved. Error: " . $broadcastError->getMessage());
            }

        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'rate limit') || str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                $this->release(30);
                return;
            }
            Log::error("OpenRouter Correction FAILED for Detail ID: {$detail->id}. Error: " . $e->getMessage());
            throw $e;
        }
    }

    protected function updateSessionTotals(ExamSession $session)
    {
        // Use direct DB queries to avoid stale collection data
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

        // Update ExamResult
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

    protected function updateQuestionCorrectionProgress($examId, $questionId)
    {
        $correction = ExamQuestionCorrection::where('exam_id', $examId)
            ->where('exam_question_id', $questionId)
            ->first();

        if ($correction) {
            $correction->increment('corrected_count');

            if ($correction->corrected_count >= $correction->total_to_correct) {
                $correction->update(['status' => CorrectionStatusEnum::COMPLETED]);
            }
        }
    }
}
