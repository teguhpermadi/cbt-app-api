<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ExamCorrectionResource;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamCorrectionController extends ApiController
{
    /**
     * Get all sessions for a specific exam.
     */
    public function index(Request $request, Exam $exam)
    {
        $sessions = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->with(['user'])
            ->get(); // Removed latest() to keep order more stable if preferred, but usually name is better for stability

        $questions = ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->orderBy('question_number', 'asc')
            ->get();

        return $this->success([
            'exam' => $exam,
            'sessions' => \App\Http\Resources\Student\ExamSessionResource::collection($sessions),
            'questions' => $questions
        ]);
    }

    /**
     * Get all answers for a specific session for correction.
     */
    public function show(Exam $exam, ExamSession $examSession)
    {
        // Ensure session belongs to exam
        if ($examSession->exam_id !== $exam->id) {
            abort(404, 'Session not found for this exam.');
        }

        $examSession->load(['user', 'exam']);

        $details = $examSession->examResultDetails()
            ->with(['examQuestion'])
            ->orderBy('question_number')
            ->get();

        return $this->success([
            'session' => new \App\Http\Resources\Student\ExamSessionResource($examSession),
            'exam' => new \App\Http\Resources\ExamResource($examSession->exam),
            'answers' => ExamCorrectionResource::collection($details),
        ]);
    }

    /**
     * Get all student answers for a specific exam question.
     */
    public function byQuestion(Exam $exam, ExamQuestion $examQuestion)
    {
        // Ensure question belongs to exam
        if ($examQuestion->exam_id !== $exam->id) {
            abort(404, 'Question not found for this exam.');
        }

        $details = ExamResultDetail::query()
            ->where('exam_question_id', $examQuestion->id)
            ->with(['examSession.user', 'examQuestion'])
            ->get();

        return $this->success([
            'question' => $examQuestion,
            'answers' => ExamCorrectionResource::collection($details),
        ]);
    }

    /**
     * Update score and notes for a specific answer.
     */
    public function update(Request $request, ExamSession $examSession, ExamResultDetail $examResultDetail)
    {
        // Ensure detail belongs to session
        if ($examResultDetail->exam_session_id !== $examSession->id) {
            abort(404, 'Answer detail not found for this session.');
        }

        $validated = $request->validate([
            'score_earned' => 'required|numeric|min:0',
            'correction_notes' => 'nullable|string',
            'is_correct' => 'nullable|boolean',
        ]);

        $maxScore = $examResultDetail->examQuestion->score_value;

        if ($validated['score_earned'] > $maxScore) {
            return $this->error("Score cannot exceed maximum score of {$maxScore}", 422);
        }

        $examResultDetail->update([
            'score_earned' => $validated['score_earned'],
            'correction_notes' => $validated['correction_notes'],
            'is_correct' => $validated['is_correct'] ?? ($validated['score_earned'] == $maxScore),
        ]);

        return $this->success(
            new ExamCorrectionResource($examResultDetail),
            'Correction updated successfully'
        );
    }

    /**
     * Finish correction, recalculate totals, and update status.
     */
    public function finish(ExamSession $examSession)
    {
        DB::transaction(function () use ($examSession) {
            // Recalculate total score from details
            $totalScore = $examSession->examResultDetails()->sum('score_earned');

            $examSession->update([
                'total_score' => $totalScore,
                'is_corrected' => true,
            ]);

            // Update generic ExamResult if exists (usually best attempt or passed logic handling specific to school rules)
            // Here we assume we might need to update the main ExamResult record if this session is the 'official' one.
            $examResult = $examSession->examResult; // HasOne relation
            if ($examResult) {
                $percent = 0;
                if ($examSession->total_max_score > 0) {
                    $percent = ($totalScore / $examSession->total_max_score) * 100;
                }

                $examResult->update([
                    'total_score' => $totalScore,
                    'score_percent' => $percent,
                    'is_passed' => $percent >= $examSession->exam->passing_grade,
                ]);
            }
        });

        return $this->success(
            $examSession->fresh(),
            'Correction finished and scores updated.'
        );
    }
}
