<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use App\Services\ExamScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class BackupController extends ApiController
{
    public function restoreExamAnswers(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json,txt|max:5120',
        ]);

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();

        try {
            $jsonContent = file_get_contents($file->getRealPath());
            $data = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->error('Invalid JSON format.', 422);
            }

            $requiredFields = ['examId', 'studentId', 'questions'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    return $this->error("Missing required field: {$field}", 422);
                }
            }

            $examId = $data['examId'];
            $studentId = $data['studentId'];
            $questions = $data['questions'];
            $totalQuestions = count($questions);

            $session = ExamSession::where('exam_id', $examId)
                ->where('user_id', $studentId)
                ->first();

            if (!$session) {
                return $this->error(
                    'No exam session found for this student and exam.',
                    404
                );
            }

            if ($session->is_finished) {
                return $this->error(
                    'Cannot restore: exam session is already finished.',
                    422
                );
            }

            $scoringService = new ExamScoringService();
            $restoredCount = 0;
            $skippedCount = 0;
            $totalScoreEarned = 0;
            $details = [];

            DB::transaction(function () use ($session, $questions, $scoringService, &$restoredCount, &$skippedCount, &$totalScoreEarned, &$details) {
                foreach ($questions as $q) {
                    $questionId = $q['questionId'] ?? null;
                    $studentAnswer = $q['studentAnswer'] ?? null;
                    $isFlagged = $q['isFlagged'] ?? false;

                    if (!$questionId) {
                        $skippedCount++;
                        continue;
                    }

                    $detail = ExamResultDetail::where('id', $questionId)
                        ->where('exam_session_id', $session->id)
                        ->first();

                    if (!$detail) {
                        $skippedCount++;
                        $details[] = [
                            'questionId' => $questionId,
                            'status' => 'skipped',
                            'reason' => 'Question not found in this session'
                        ];
                        continue;
                    }

                    $previousAnswer = $detail->student_answer;
                    $detail->student_answer = $studentAnswer;
                    $detail->is_flagged = $isFlagged;
                    $detail->answered_at = now();

                    $result = $scoringService->calculateDetailScore($detail);
                    $detail->is_correct = $result['is_correct'];
                    $detail->score_earned = $result['score'];
                    $detail->save();

                    $totalScoreEarned += $result['score'];
                    $restoredCount++;

                    $details[] = [
                        'questionId' => $questionId,
                        'questionNumber' => $detail->question_number,
                        'status' => 'restored',
                        'scoreEarned' => $result['score']
                    ];
                }

                $session->total_score = $totalScoreEarned;
                $session->save();
            });

            $exam = $session->exam;

            return $this->success([
                'examSessionId' => $session->id,
                'examTitle' => $exam->title ?? 'Unknown',
                'studentId' => $studentId,
                'studentName' => $data['studentName'] ?? 'Unknown',
                'totalQuestions' => $totalQuestions,
                'restoredCount' => $restoredCount,
                'skippedCount' => $skippedCount,
                'newTotalScore' => $totalScoreEarned,
                'filename' => $filename,
                'restoredAt' => now()->toIso8601String()
            ], 'Exam answers restored successfully.');

        } catch (Throwable $e) {
            return $this->error('Failed to restore exam answers: ' . $e->getMessage(), 500);
        }
    }
}
