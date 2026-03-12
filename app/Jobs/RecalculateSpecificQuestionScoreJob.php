<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use App\Services\ExamScoringService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateSpecificQuestionScoreJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $examId;
    private string $examQuestionId;

    /**
     * Create a new job instance.
     *
     * @param string $examId
     * @param string $examQuestionId
     */
    public function __construct(string $examId, string $examQuestionId)
    {
        $this->examId = $examId;
        $this->examQuestionId = $examQuestionId;
    }

    /**
     * Execute the job.
     */
    public function handle(ExamScoringService $scoringService): void
    {
        Log::info("Starting bulk recalculation for Question: {$this->examQuestionId} in Exam: {$this->examId}");

        $exam = Exam::find($this->examId);
        $examQuestion = ExamQuestion::find($this->examQuestionId);

        if (!$exam || !$examQuestion) {
            Log::warning("Exam or ExamQuestion not found for recalculation.");
            return;
        }

        // Get all details for this specific question across all sessions
        $details = ExamResultDetail::where('exam_question_id', $this->examQuestionId)
            ->whereHas('examSession', function ($query) {
                $query->where('exam_id', $this->examId);
            })
            ->with(['examQuestion', 'examSession'])
            ->get();

        Log::info("Found {$details->count()} student answers to recalculate.");

        $affectedSessionIds = [];

        DB::transaction(function () use ($details, $scoringService, &$affectedSessionIds) {
            foreach ($details as $detail) {
                // Determine the score for this specific detail
                $result = $scoringService->calculateDetailScore($detail);

                $scoreEarned = (float)$result['score'];
                $isCorrect = (bool)$result['is_correct'];

                // Update the detail record
                $detail->update([
                    'is_correct' => $isCorrect,
                    'score_earned' => $scoreEarned,
                ]);

                // Track which sessions were affected so we can update their totals later
                if (!in_array($detail->exam_session_id, $affectedSessionIds)) {
                    $affectedSessionIds[] = $detail->exam_session_id;
                }
            }

            // Now update the total_score and ExamResult for every affected session
            foreach ($affectedSessionIds as $sessionId) {
                $session = ExamSession::with(['examResultDetails.examQuestion', 'exam'])->find($sessionId);
                if (!$session) continue;

                $totalMaxScore = 0;
                $totalEarnedScore = 0;

                foreach ($session->examResultDetails as $sDetail) {
                    $maxScore = $sDetail->examQuestion->score_value ?? 0;
                    $totalMaxScore += $maxScore;
                    $totalEarnedScore += $sDetail->score_earned;
                }

                if ($totalEarnedScore < 0) {
                    $totalEarnedScore = 0;
                }

                $session->update([
                    'total_score' => $totalEarnedScore,
                    'total_max_score' => $totalMaxScore
                ]);

                // Update ExamResult percent
                $scorePercent = $totalMaxScore > 0 ? round(($totalEarnedScore / $totalMaxScore) * 100, 1) : 0;

                $existingResult = ExamResult::where('exam_id', $session->exam_id)
                    ->where('user_id', $session->user_id)
                    ->first();

                $shouldUpdate = !$existingResult
                    || $existingResult->exam_session_id === $session->id
                    || $scorePercent > $existingResult->score_percent;

                if ($shouldUpdate) {
                    $resultType = $existingResult ? \App\Enums\ExamResultTypeEnum::BEST_ATTEMPT : \App\Enums\ExamResultTypeEnum::OFFICIAL;

                    if ($existingResult && $existingResult->exam_session_id === $session->id) {
                        $resultType = $existingResult->result_type;
                    }

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
                            'result_type' => $resultType,
                        ]
                    );
                }
            }
        });

        Log::info("Bulk recalculation finished. Updated {$details->count()} answers across " . count($affectedSessionIds) . " sessions.");
    }
}
