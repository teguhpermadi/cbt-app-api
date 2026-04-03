<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QuestionTypeEnum;
use App\Models\ExamResult;
use App\Models\ExamSession;
use BackedEnum;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use romanzipp\QueueMonitor\Traits\IsMonitored;

final class CalculateExamScoreJob implements ShouldQueue
{
    use IsMonitored, Queueable;

    private $session;

    private $questionTypes; // Changed from singular to plural conceptual usage, but can be array

    /**
     * Create a new job instance.
     *
     * @param  string|array  $questionTypes
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

        DB::transaction(function () use ($session, &$totalMaxScore, &$totalEarnedScore) {
            foreach ($session->examResultDetails as $detail) {
                $examQuestion = $detail->examQuestion;

                $maxScore = $examQuestion->score_value ?? 0;
                $totalMaxScore += $maxScore;

                // Sum up already earned score instead of recalculating (correcting)
                $scoreEarned = $detail->score_earned ?? 0;
                $totalEarnedScore += $scoreEarned;

                $qTypeValue = $examQuestion->question_type instanceof BackedEnum
                    ? $examQuestion->question_type->value
                    : $examQuestion->question_type;

                if (in_array($qTypeValue, ['essay', 'short_answer'])) {
                    Log::info('Calculating '.ucfirst(str_replace('_', ' ', $qTypeValue))." Detail {$detail->id}. Current Score: {$scoreEarned}");
                }

                $essayTypes = [
                    QuestionTypeEnum::ESSAY->value,
                    QuestionTypeEnum::SHORT_ANSWER->value,
                    QuestionTypeEnum::ARABIC_RESPONSE->value,
                    QuestionTypeEnum::JAVANESE_RESPONSE->value,
                ];

                if (in_array($qTypeValue, $essayTypes) && $detail->student_answer !== null && empty($detail->correction_notes)) {
                    CorrectExamQuestionLMStudioJob::dispatch($detail, 'CalculateExamScoreJob');
                }
            }

            // Finalize Total Score (Floor at 0)
            if ($totalEarnedScore < 0) {
                $totalEarnedScore = 0;
            }

            // Update ExamSession total_score AND total_max_score
            $session->update([
                'total_score' => $totalEarnedScore,
                'total_max_score' => $totalMaxScore,
            ]);

            // Update or Create ExamResult
            $scorePercent = $totalMaxScore > 0 ? round(($totalEarnedScore / $totalMaxScore) * 100, 1) : 0;

            $existingResult = ExamResult::where('exam_id', $session->exam_id)
                ->where('user_id', $session->user_id)
                ->first();

            $shouldUpdate = ! $existingResult
                || $existingResult->exam_session_id === $session->id
                || $scorePercent > $existingResult->score_percent;

            if ($shouldUpdate) {
                $resultType = $existingResult ? \App\Enums\ExamResultTypeEnum::BEST_ATTEMPT : \App\Enums\ExamResultTypeEnum::OFFICIAL;

                // If it already exists and we are just updating the same session's score,
                // keep its current result_type (so we don't accidentally overwrite OFFICIAL to BEST_ATTEMPT if it was OFFICIAL)
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
        });

        $typeLog = is_array($this->questionTypes) ? json_encode($this->questionTypes) : $this->questionTypes;
        Log::info("Exam score calculated for session: {$session->id} (Type: {$typeLog})", [
            'user_id' => $session->user_id,
            'exam_id' => $session->exam_id,
            'total_score' => $totalEarnedScore,
        ]);
    }
}
