<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExamCorrectionResource;
use App\Models\Exam;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamCorrectionController extends Controller
{
    /**
     * Get all sessions for a specific exam.
     */
    public function index(Request $request, Exam $exam)
    {
        $sessions = ExamSession::query()
            ->where('exam_id', $exam->id)
            ->with(['user'])
            ->latest()
            ->get();

        return \App\Http\Resources\Student\ExamSessionResource::collection($sessions);
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

        return response()->json([
            'session' => (new \App\Http\Resources\Student\ExamSessionResource($examSession))->resolve(),
            'answers' => ExamCorrectionResource::collection($details)->resolve(),
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
            return response()->json([
                'message' => "Score cannot exceed maximum score of {$maxScore}",
            ], 422);
        }

        $examResultDetail->update([
            'score_earned' => $validated['score_earned'],
            'correction_notes' => $validated['correction_notes'],
            'is_correct' => $validated['is_correct'] ?? ($validated['score_earned'] == $maxScore),
        ]);

        return new ExamCorrectionResource($examResultDetail);
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

        return response()->json([
            'message' => 'Correction finished and scores updated.',
            'data' => $examSession->fresh(),
        ]);
    }
}
