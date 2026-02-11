<?php


namespace App\Jobs;

use App\Models\ExamSession;
use App\Models\ExamResult;
use App\Models\ExamResultDetail;
use App\Enums\QuestionTypeEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateExamScoreJob implements ShouldQueue
{
    use Queueable;

    private $session;
    private $questionTypes; // Changed from singular to plural conceptual usage, but can be array

    /**
     * Create a new job instance.
     * @param string|array $questionTypes
     */
    public function __construct(ExamSession $session, $questionTypes = 'all')
    {
        $this->session = $session;
        // Normalize to array if it's not 'all'
        if ($questionTypes === 'all') {
            $this->questionTypes = 'all';
        } else {
            $this->questionTypes = is_array($questionTypes) ? $questionTypes : [$questionTypes];
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $session = $this->session->load(['exam', 'examResultDetails.examQuestion']);
        $exam = $session->exam;

        $totalMaxScore = 0;
        $totalEarnedScore = 0;

        $scoringService = new \App\Services\ExamScoringService();

        DB::transaction(function () use ($session, $scoringService, &$totalMaxScore, &$totalEarnedScore) {
            foreach ($session->examResultDetails as $detail) {
                $examQuestion = $detail->examQuestion;

                // --- FILTER LOGIC ---
                // If questionType is NOT 'all' AND it does not match this question's type,
                // we skip recalculating but still must accumulate existing scores.

                // Note: $examQuestion->question_type is an Enum in the model cast.
                // We use ->value to get the string.
                $qTypeValue = $examQuestion->question_type instanceof \BackedEnum
                    ? $examQuestion->question_type->value
                    : $examQuestion->question_type;

                $shouldRecalculate = false;
                if ($this->questionTypes === 'all') {
                    $shouldRecalculate = true;
                } elseif (is_array($this->questionTypes) && in_array($qTypeValue, $this->questionTypes)) {
                    $shouldRecalculate = true;
                }

                if (!$shouldRecalculate) {
                    $maxScore = $examQuestion->score_value ?? 0;
                    $totalMaxScore += $maxScore;
                    $totalEarnedScore += $detail->score_earned;
                    continue;
                }
                // --------------------

                $maxScore = $examQuestion->score_value ?? 0;
                $totalMaxScore += $maxScore;

                // Process the score for this question
                $result = $scoringService->calculateDetailScore($detail);

                $scoreEarned = $result['score'];
                $isCorrect = $result['is_correct'];

                if ($qTypeValue === 'essay') {
                    Log::info("Recalculating Essay Detail {$detail->id}. Existing Score: {$detail->score_earned}, Preserved Score: {$scoreEarned}");
                }

                $detail->update([
                    'is_correct' => $isCorrect,
                    'score_earned' => $scoreEarned,
                ]);

                $totalEarnedScore += $scoreEarned;
            }

            // Finalize Total Score (Floor at 0)
            if ($totalEarnedScore < 0) {
                $totalEarnedScore = 0;
            }

            // Update ExamSession total_score AND total_max_score
            $session->update([
                'total_score' => $totalEarnedScore,
                'total_max_score' => $totalMaxScore
            ]);

            // Update or Create ExamResult
            $scorePercent = $totalMaxScore > 0 ? round(($totalEarnedScore / $totalMaxScore) * 100, 1) : 0;

            $existingResult = ExamResult::where('exam_id', $session->exam_id)
                ->where('user_id', $session->user_id)
                ->first();

            $shouldUpdate = !$existingResult || $scorePercent > $existingResult->score_percent;

            if ($shouldUpdate) {
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
                        'result_type' => $existingResult ? \App\Enums\ExamResultTypeEnum::BEST_ATTEMPT : \App\Enums\ExamResultTypeEnum::OFFICIAL,
                    ]
                );
            }
        });

        $typeLog = is_array($this->questionTypes) ? json_encode($this->questionTypes) : $this->questionTypes;
        Log::info("Exam score calculated for session: {$session->id} (Type: {$typeLog})", [
            'user_id' => $session->user_id,
            'exam_id' => $session->exam_id,
            'total_score' => $totalEarnedScore,
        ]);
    }
}
