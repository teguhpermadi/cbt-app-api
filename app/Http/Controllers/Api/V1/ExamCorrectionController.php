<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CorrectionStatusEnum;
use App\Enums\QuestionTypeEnum;
use App\Enums\UserTypeEnum;
use App\Events\AiCorrectionFinished;
use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ExamCorrectionResource;
use App\Models\AiCorrectionStat;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamQuestionCorrection;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use App\Models\User;
use App\Notifications\AiCorrectionFinishedNotification;
use App\Services\ExamScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

final class ExamCorrectionController extends ApiController
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

        // Ensure correction statuses are up to date for all questions
        foreach ($questions as $question) {
            $this->syncQuestionCorrectionProgress($exam, $question);
        }

        $correctionStatuses = ExamQuestionCorrection::query()
            ->where('exam_id', $exam->id)
            ->get();

        return $this->success([
            'exam' => $exam,
            'sessions' => \App\Http\Resources\Student\ExamSessionResource::collection($sessions),
            'questions' => $questions,
            'correction_statuses' => $correctionStatuses,
        ]);
    }

    /**
     * Get all answers for a specific session for correction.
     */
    public function show(Request $request, Exam $exam, ExamSession $examSession)
    {
        $user = Auth::user();

        // Authorization check
        if ($user->user_type === UserTypeEnum::STUDENT) {
            // Student can only view their own session
            if ($examSession->user_id !== $user->id) {
                return $this->error('You can only view your own results.', 403);
            }

            // Student can only view if exam's is_show_result is true
            if (! $exam->is_show_result) {
                return $this->error('Detailed results are not available for this exam.', 403);
            }
        }

        // Ensure session belongs to exam
        if ($examSession->exam_id !== $exam->id) {
            abort(404, 'Session not found for this exam.');
        }

        $examSession->load(['user.classrooms', 'exam.subject', 'exam.classrooms']);

        $details = $examSession->examResultDetails()
            ->join('exam_questions', 'exam_result_details.exam_question_id', '=', 'exam_questions.id')
            ->with(['examQuestion.originalQuestion.tags'])
            ->select('exam_result_details.*')
            ->orderBy('exam_questions.question_number')
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
            ->join('exam_questions', 'exam_result_details.exam_question_id', '=', 'exam_questions.id')
            ->join('exam_sessions', 'exam_result_details.exam_session_id', '=', 'exam_sessions.id')
            ->join('users', 'exam_sessions.user_id', '=', 'users.id')
            ->where('exam_result_details.exam_question_id', $examQuestion->id)
            ->with(['examSession.user', 'examQuestion'])
            ->select('exam_result_details.*')
            ->orderBy('users.name', 'asc')
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
            'score_earned' => 'nullable|numeric|min:0',
            'marking_status' => 'nullable|string|in:full,partial,no',
            'correction_notes' => 'nullable|string',
            'is_correct' => 'nullable|boolean',
        ]);

        $maxScore = $examResultDetail->examQuestion->score_value;
        $scoreEarned = $validated['score_earned'] ?? $examResultDetail->score_earned;
        $isCorrect = $validated['is_correct'] ?? $examResultDetail->is_correct;

        // Handle marking status shortcuts
        if (isset($validated['marking_status'])) {
            if ($validated['marking_status'] === 'full') {
                $scoreEarned = $maxScore;
                $isCorrect = true;
            } elseif ($validated['marking_status'] === 'no') {
                $scoreEarned = 0;
                $isCorrect = false;
            }
        }

        if ($scoreEarned > $maxScore) {
            return $this->error("Score cannot exceed maximum score of {$maxScore}", 422);
        }

        $examResultDetail->update([
            'score_earned' => $scoreEarned,
            'correction_notes' => $validated['correction_notes'] ?? $examResultDetail->correction_notes,
            'is_correct' => $isCorrect ?? ($scoreEarned === $maxScore),
        ]);

        // Sync progress
        $this->syncQuestionCorrectionProgress($examSession->exam, $examResultDetail->examQuestion);

        return $this->success(
            new ExamCorrectionResource($examResultDetail),
            'Correction updated successfully'
        );
    }

    /**
     * Update the student's answer for a specific question.
     */
    public function updateAnswer(Request $request, Exam $exam, ExamSession $examSession, ExamResultDetail $examResultDetail)
    {
        $user = Auth::user();

        if ($user->user_type === UserTypeEnum::STUDENT) {
            return $this->error('You do not have permission to update student answers.', 403);
        }

        if ($examSession->exam_id !== $exam->id) {
            abort(404, 'Session not found for this exam.');
        }

        if ($examResultDetail->exam_session_id !== $examSession->id) {
            abort(404, 'Answer detail not found for this session.');
        }

        $validated = $request->validate([
            'student_answer' => 'required', // can be string or array/json
        ]);

        $examResultDetail->update([
            'student_answer' => $validated['student_answer'],
            'answered_at' => now(), // update timestamp as if student answered it
        ]);

        return $this->success(
            new ExamCorrectionResource($examResultDetail),
            'Student answer updated successfully'
        );
    }

    /**
     * Reopen a finished exam session based on a detail ID.
     */
    public function reopenByDetail(Request $request, ExamSession $examSession, ExamResultDetail $examResultDetail)
    {
        // Ensure detail belongs to session
        if ($examResultDetail->exam_session_id !== $examSession->id) {
            abort(404, 'Answer detail not found for this session.');
        }

        if (! $examSession->is_finished) {
            return $this->error('Session is already active.', 400);
        }

        $request->validate([
            'minutes' => 'nullable|integer|min:0',
        ]);

        // Smart Reopen Logic (from ExamController)
        $now = now();
        $startTime = \Carbon\Carbon::parse($examSession->start_time);
        $minutesSinceStart = $now->diffInMinutes($startTime);
        $requestedMinutes = $request->minutes ?? 15; // Default to 15 mins if not provided
        $exam = $examSession->exam;
        $neededExtraTime = ($minutesSinceStart + $requestedMinutes) - $exam->duration;

        $newExtraTime = max($examSession->extra_time ?? 0, (int) $neededExtraTime);

        $examSession->update([
            'is_finished' => false,
            'finish_time' => null,
            'extra_time' => $newExtraTime,
        ]);

        // Dispatch TimerSynchronized event
        $remainingSeconds = $examSession->getRemainingSeconds();
        event(new \App\Events\TimerSynchronized($exam->id, $examSession->user_id, $remainingSeconds));

        // Dispatch LiveScoreUpdated event
        event(new \App\Events\LiveScoreUpdated($exam->id, $examSession->getBroadcastData()));

        return $this->success(
            new \App\Http\Resources\Student\ExamSessionResource($examSession),
            'Exam session reopened successfully.'
        );
    }

    /**
     * Bulk update scores for multiple answers.
     */
    public function bulkUpdate(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|exists:exam_result_details,id',
            'updates.*.score_earned' => 'nullable|numeric|min:0',
            'updates.*.marking_status' => 'nullable|string|in:full,partial,no',
            'updates.*.correction_notes' => 'nullable|string',
            'updates.*.is_correct' => 'nullable|boolean',
        ]);

        $updatedCount = 0;

        DB::transaction(function () use ($validated, $exam, &$updatedCount) {
            foreach ($validated['updates'] as $updateData) {
                $detail = ExamResultDetail::with('examQuestion')->find($updateData['id']);

                if (! $detail) {
                    continue;
                }

                $maxScore = $detail->examQuestion->score_value;
                $scoreEarned = $updateData['score_earned'] ?? $detail->score_earned;
                $isCorrect = $updateData['is_correct'] ?? $detail->is_correct;

                if (isset($updateData['marking_status'])) {
                    if ($updateData['marking_status'] === 'full') {
                        $scoreEarned = $maxScore;
                        $isCorrect = true;
                    } elseif ($updateData['marking_status'] === 'no') {
                        $scoreEarned = 0;
                        $isCorrect = false;
                    }
                }

                $detail->update([
                    'score_earned' => min($scoreEarned, $maxScore),
                    'correction_notes' => $updateData['correction_notes'] ?? $detail->correction_notes,
                    'is_correct' => $isCorrect ?? ($scoreEarned === $maxScore),
                ]);

                // Sync progress for this question
                $this->syncQuestionCorrectionProgress($exam, $detail->examQuestion);

                $updatedCount++;
            }
        });

        return $this->success(null, "Successfully updated {$updatedCount} answers.");
    }

    /**
     * Sync correction progress for a specific question.
     */
    public function syncQuestionCorrectionProgress(Exam $exam, ExamQuestion $question)
    {
        // For non-essay/short-answer questions, they are usually auto-corrected
        // But we might want to track them anyway.

        $totalToCorrect = ExamResultDetail::where('exam_question_id', $question->id)
            ->whereHas('examSession', function ($q) {
                $q->where('is_finished', true);
            })
            ->count();

        if ($totalToCorrect === 0) {
            return null;
        }

        $correctedCount = ExamResultDetail::where('exam_question_id', $question->id)
            ->whereHas('examSession', function ($q) {
                $q->where('is_finished', true);
            })
            ->whereNotNull('score_earned')
            ->count();

        $status = CorrectionStatusEnum::PENDING;
        if ($correctedCount >= $totalToCorrect) {
            $status = CorrectionStatusEnum::COMPLETED;
        }

        // Check if there's an existing record (perhaps marked as processing by AI)
        $correction = ExamQuestionCorrection::where('exam_id', $exam->id)
            ->where('exam_question_id', $question->id)
            ->first();

        if ($correction && $correction->status === CorrectionStatusEnum::PROCESSING) {
            // Keep processing status if AI is currently working, but update counts
            $correction->update([
                'total_to_correct' => $totalToCorrect,
                'corrected_count' => $correctedCount,
            ]);
        } else {
            $correction = ExamQuestionCorrection::updateOrCreate(
                ['exam_id' => $exam->id, 'exam_question_id' => $question->id],
                [
                    'status' => $status,
                    'total_to_correct' => $totalToCorrect,
                    'corrected_count' => $correctedCount,
                ]
            );
        }

        return $correction;
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

    /**
     * Recalculate all scores for a specific session.
     */
    public function recalculate(ExamSession $examSession)
    {
        // Use the existing CalculateExamScoreJob to recalculate all scores
        \App\Jobs\CalculateExamScoreJob::dispatchSync($examSession, 'all');

        return $this->success(
            $examSession->fresh(['examResult']),
            'Scores recalculated successfully.'
        );
    }

    /**
     * Recalculate all scores for all sessions in an exam.
     */
    public function recalculateAll(Exam $exam)
    {
        $sessions = ExamSession::where('exam_id', $exam->id)->get();

        foreach ($sessions as $session) {
            \App\Jobs\CalculateExamScoreJob::dispatchSync($session, 'all');
        }

        return $this->success(
            null,
            "Successfully recalculated scores for {$sessions->count()} sessions."
        );
    }

    /**
     * Telaah Soal (Item Analysis)
     */
    public function itemAnalysis(Exam $exam)
    {
        // Get all finished or corrected sessions for this exam
        $sessions = ExamSession::where('exam_id', $exam->id)
            ->where(function ($q) {
                $q->where('is_finished', true)->orWhere('is_corrected', true);
            })
            ->orderByDesc('total_score')
            ->get();

        $totalStudents = $sessions->count();

        if ($totalStudents < 3) {
            return $this->error('Data tidak cukup untuk melakukan telaah soal. Minimal 3 siswa diperlukan.', 400);
        }

        $topCount = max(1, (int) round($totalStudents * 0.27));
        $bottomCount = max(1, (int) round($totalStudents * 0.27));

        $topSessions = $sessions->take($topCount)->pluck('id')->toArray();
        $bottomSessions = $sessions->slice(-$bottomCount)->pluck('id')->toArray();

        $questions = ExamQuestion::where('exam_id', $exam->id)
            ->with(['originalQuestion.options'])
            ->orderBy('question_number')
            ->get();

        $analysisData = [];
        $statusCounts = [
            'Diterima' => 0,
            'Direvisi' => 0,
            'Ditolak' => 0,
        ];

        foreach ($questions as $question) {
            $details = ExamResultDetail::where('exam_question_id', $question->id)->get();

            $totalAnswered = $details->count();
            if ($totalAnswered === 0) {
                continue;
            }

            $maxScore = max(1, (float) ($question->score_value ?? 1));

            // 1. Tingkat Kesukaran (P) - Menggunakan rerata skor untuk mendukung soal uraian/isian singkat
            $totalScoreEarned = $details->sum('score_earned');
            $difficultyScore = $totalScoreEarned / ($totalAnswered * $maxScore);

            $difficultyCategory = 'Sedang';
            if ($difficultyScore < 0.3) {
                $difficultyCategory = 'Sukar';
            } elseif ($difficultyScore > 0.7) {
                $difficultyCategory = 'Mudah';
            }

            // 2. Daya Beda (D) - Membedakan rerata kelompok atas dan bawah
            $topScoreEarned = $details->whereIn('exam_session_id', $topSessions)->sum('score_earned');
            $bottomScoreEarned = $details->whereIn('exam_session_id', $bottomSessions)->sum('score_earned');

            $discriminationScore = 0;
            if ($topCount > 0 && $bottomCount > 0) {
                $meanTop = $topScoreEarned / $topCount;
                $meanBottom = $bottomScoreEarned / $bottomCount;
                $discriminationScore = ($meanTop - $meanBottom) / $maxScore;
            }

            $discriminationCategory = 'Cukup';
            if ($discriminationScore < 0.2) {
                $discriminationCategory = 'Jelek';
            } elseif ($discriminationScore >= 0.4 && $discriminationScore < 0.7) {
                $discriminationCategory = 'Baik';
            } elseif ($discriminationScore >= 0.7) {
                $discriminationCategory = 'Sangat Baik';
            }

            // 3. Efektivitas Pengecoh
            $distractorStatus = 'Tidak Berlaku';
            $distractorNote = '';

            if ($question->question_type === 'multiple_choice' && $question->originalQuestion) {
                $options = $question->originalQuestion->options;
                $optionsCount = $options->count();
                $minChosen = max(1, (int) round(0.05 * $totalStudents)); // 5% rule

                $badDistractors = 0;
                $distractorDetails = [];

                foreach ($options as $option) {
                    if ($option->is_correct) {
                        continue;
                    }

                    // How many chose this option
                    $chosenByAll = $details->filter(function ($d) use ($option) {
                        return is_array($d->student_answer) && in_array($option->id, $d->student_answer);
                    })->count();

                    $chosenByTop = $details->whereIn('exam_session_id', $topSessions)->filter(function ($d) use ($option) {
                        return is_array($d->student_answer) && in_array($option->id, $d->student_answer);
                    })->count();

                    $chosenByBottom = $details->whereIn('exam_session_id', $bottomSessions)->filter(function ($d) use ($option) {
                        return is_array($d->student_answer) && in_array($option->id, $d->student_answer);
                    })->count();

                    $isFunctional = ($chosenByAll >= $minChosen) && ($chosenByBottom >= $chosenByTop);

                    if (! $isFunctional) {
                        $badDistractors++;
                    }

                    $distractorDetails[] = [
                        'option_id' => $option->id,
                        'chosen_all' => $chosenByAll,
                        'chosen_top' => $chosenByTop,
                        'chosen_bottom' => $chosenByBottom,
                        'is_functional' => $isFunctional,
                    ];
                }

                if ($badDistractors === 0) {
                    $distractorStatus = 'Sangat Baik';
                } elseif ($badDistractors <= ($optionsCount - 1) / 2) {
                    $distractorStatus = 'Berfungsi';
                } else {
                    $distractorStatus = 'Kurang Berfungsi';
                }
            }

            // 4. Kesimpulan dan Rekomendasi
            $status = 'Diterima';
            $recommendation = "Soal ini sudah baik karena memiliki tingkat kesukaran {$difficultyCategory} dan daya beda {$discriminationCategory}.";

            if ($discriminationScore < 0.2) {
                if ($difficultyScore < 0.3) {
                    $status = 'Ditolak';
                    $recommendation = 'Soal ini terlalu sukar dan tidak membedakan siswa (daya beda jelek). Sebaiknya dibuang atau dirombak total.';
                } elseif ($difficultyScore > 0.7) {
                    $status = 'Direvisi';
                    $recommendation = 'Soal ini terlalu mudah dan daya bedanya jelek. Perlu ditambah tingkat kesukarannya.';
                } else {
                    $status = 'Direvisi';
                    $recommendation = 'Tingkat kesukaran sedang, tetapi daya bedanya jelek (mungkin membingungkan siswa pandai). Perlu perbaikan pada redaksi soal atau pengecoh.';
                }
            } elseif ($discriminationScore < 0.3) {
                $status = 'Direvisi';
                $recommendation = "Daya beda soal ini {$discriminationCategory}. Soal dapat diterima dengan sedikit perbaikan kalimat.";
            }

            if ($distractorStatus === 'Kurang Berfungsi') {
                $status = ($status === 'Diterima') ? 'Direvisi' : $status;
                $recommendation .= ' Beberapa pengecoh tidak berfungsi dengan baik (kurang diminati atau lebih mengecoh kelompok atas).';
            }

            $statusCounts[$status]++;

            $analysisData[] = [
                'question_id' => $question->id,
                'question_number' => $question->question_number,
                'question_type' => $question->question_type,
                'content' => $question->content,
                'difficulty' => [
                    'score' => round($difficultyScore, 2),
                    'category' => $difficultyCategory,
                ],
                'discrimination' => [
                    'score' => round($discriminationScore, 2),
                    'category' => $discriminationCategory,
                ],
                'distractor' => [
                    'status' => $distractorStatus,
                    'details' => $distractorDetails ?? [],
                ],
                'conclusion' => [
                    'status' => $status,
                    'recommendation' => $recommendation,
                ],
            ];
        }

        $totalExamQuestions = $questions->count();
        $acceptedPercent = $totalExamQuestions > 0 ? round(($statusCounts['Diterima'] / $totalExamQuestions) * 100) : 0;

        $overallRecommendation = 'Instrumen tes memerlukan banyak perbaikan.';
        if ($acceptedPercent >= 80) {
            $overallRecommendation = 'Secara umum, instrumen tes sudah sangat baik dan layak diujikan kembali.';
        } elseif ($acceptedPercent >= 50) {
            $overallRecommendation = 'Instrumen tes cukup baik, namun beberapa soal perlu direvisi sebelum digunakan kembali.';
        } else {
            $overallRecommendation = 'Sebagian besar butir soal kurang relevan atau membingungkan. Sangat disarankan untuk merombak ulang instrumen tes.';
        }

        return $this->success([
            'exam_id' => $exam->id,
            'total_students' => $totalStudents,
            'top_group_size' => $topCount,
            'bottom_group_size' => $bottomCount,
            'summary' => [
                'total_questions' => $totalExamQuestions,
                'accepted' => $statusCounts['Diterima'],
                'revised' => $statusCounts['Direvisi'],
                'rejected' => $statusCounts['Ditolak'],
                'general_recommendation' => $overallRecommendation,
            ],
            'item_analysis' => $analysisData,
        ]);
    }

    /**
     * Delete a specific exam session.
     */
    public function destroy(Exam $exam, ExamSession $examSession)
    {
        // Ensure session belongs to exam
        if ($examSession->exam_id !== $exam->id) {
            abort(404, 'Session not found for this exam.');
        }

        DB::transaction(function () use ($examSession) {
            // Delete related ExamResult if exists
            if ($examSession->examResult) {
                $examSession->examResult->delete();
            }

            // Soft delete the session
            $examSession->delete();
        });

        return $this->success(null, 'Exam session deleted successfully.');
    }

    /**
     * Trigger AI correction for student answers in an exam.
     */
    public function aiCorrect(Request $request, Exam $exam)
    {
        $provider = $request->input('provider', 'gemini');
        $examQuestionId = $request->input('exam_question_id');
        $examSessionId = $request->input('exam_session_id');
        $userId = Auth::id();
        $userName = Auth::user()?->name;

        if (! in_array($provider, ['gemini', 'openrouter', 'lmstudio'])) {
            return $this->error('Invalid provider. Supported providers are: gemini, openrouter, lmstudio', 422);
        }

        $query = ExamResultDetail::whereHas('examSession', function ($query) use ($exam) {
            $query->where('exam_id', $exam->id);
        })->whereHas('examQuestion', function ($query) {
            $query->whereIn('question_type', [
                QuestionTypeEnum::ESSAY->value,
                QuestionTypeEnum::SHORT_ANSWER->value,
                QuestionTypeEnum::MATH_INPUT->value,
                QuestionTypeEnum::ARABIC_RESPONSE->value,
                QuestionTypeEnum::JAVANESE_RESPONSE->value,
            ]);
        });

        if ($examQuestionId) {
            $query->where('exam_question_id', $examQuestionId);
        }

        if ($examSessionId) {
            $query->where('exam_session_id', $examSessionId);
        }

        $onlyUncorrected = $request->boolean('only_uncorrected', false);
        if ($onlyUncorrected) {
            $query->where(function ($q) {
                $q->whereNull('is_correct')
                    ->orWhere('is_correct', false);
            });
        }

        $resultDetails = $query->get();

        if ($resultDetails->isEmpty()) {
            $msg = 'No answers found for manual correction in this selection.';
            if ($onlyUncorrected) {
                $msg = 'All selected answers have already been corrected.';
                if ($examQuestionId && $examSessionId) {
                    $msg = 'This answer has already been corrected.';
                } elseif ($examQuestionId) {
                    $msg = 'All answers for this question have been corrected.';
                } elseif ($examSessionId) {
                    $msg = 'All answers for this student have been corrected.';
                }
            } elseif ($examQuestionId && $examSessionId) {
                $msg = 'Student answer not found.';
            } elseif ($examQuestionId) {
                $msg = 'No student answers found for this question.';
            } elseif ($examSessionId) {
                $msg = 'This student has no answers to correct.';
            }

            return $this->error($msg, 404);
        }

        // Group by question to initialize tracking
        $questionsToTrack = $resultDetails->groupBy('exam_question_id');
        foreach ($questionsToTrack as $questionId => $details) {
            ExamQuestionCorrection::updateOrCreate(
                ['exam_id' => $exam->id, 'exam_question_id' => $questionId],
                [
                    'status' => CorrectionStatusEnum::PROCESSING,
                    'total_to_correct' => $details->count(),
                    'corrected_count' => 0,
                ]
            );
        }

        // Create jobs array
        $jobs = [];
        foreach ($resultDetails as $detail) {
            if ($provider === 'openrouter') {
                $jobs[] = new \App\Jobs\CorrectExamQuestionOpenRouterJob($detail, $userName, null);
            } elseif ($provider === 'lmstudio') {
                $jobs[] = new \App\Jobs\CorrectExamQuestionLMStudioJob($detail, $userName, null, null);
            } else {
                $jobs[] = new \App\Jobs\CorrectExamQuestionJob($detail, $userName, null);
            }
        }

        // Create stats record first
        $stats = AiCorrectionStat::create([
            'exam_id' => $exam->id,
            'batch_id' => null,
            'provider' => $provider,
            'total_jobs' => count($resultDetails),
            'started_at' => now(),
            'status' => 'processing',
        ]);

        $batchName = "AI Correction: {$exam->title}";
        $batch = Bus::batch($jobs)
            ->then(function (\Illuminate\Bus\Batch $batch) use ($exam, $userId, $stats) {
                // Update stats when batch completes
                $stats->update([
                    'status' => 'completed',
                    'finished_at' => now(),
                ]);

                if ($userId) {
                    $user = User::find($userId);
                    if ($user) {
                        $message = "Koreksi AI untuk ujian '{$exam->title}' telah selesai.";

                        // Database Notification
                        $user->notify(new AiCorrectionFinishedNotification($exam->id, $exam->title, $message));

                        // Real-time Event
                        event(new AiCorrectionFinished($exam->id, (string) $userId, $message));
                    }
                }
            })
            ->name($batchName)
            ->dispatch();

        // Update stats with batch_id
        $stats->update(['batch_id' => $batch->id]);

        return $this->success([
            'batch_id' => $batch->id,
            'stats_id' => $stats->id,
            'total_jobs' => $stats->total_jobs,
            'correction_statuses' => ExamQuestionCorrection::where('exam_id', $exam->id)->get(),
        ], "AI correction jobs for '{$exam->title}' have been dispatched.");
    }

    /**
     * Reset and re-correct all objective questions for an exam.
     * This does NOT affect essay, short_answer, math_input, arabic_response, javanese_response.
     */
    public function resetObjectiveCorrection(Request $request, Exam $exam)
    {
        $objectiveTypes = [
            QuestionTypeEnum::MULTIPLE_CHOICE->value,
            QuestionTypeEnum::TRUE_FALSE->value,
            QuestionTypeEnum::MULTIPLE_SELECTION->value,
            QuestionTypeEnum::MATCHING->value,
            QuestionTypeEnum::SEQUENCE->value,
            QuestionTypeEnum::ARRANGE_WORDS->value,
            QuestionTypeEnum::CATEGORIZATION->value,
        ];

        $details = ExamResultDetail::whereHas('examSession', function ($query) use ($exam) {
            $query->where('exam_id', $exam->id);
        })->whereHas('examQuestion', function ($query) use ($objectiveTypes) {
            $query->whereIn('question_type', $objectiveTypes);
        })->with('examQuestion')->get();

        if ($details->isEmpty()) {
            return $this->error('No objective questions found for this exam.', 404);
        }

        $scoringService = new ExamScoringService();
        $correctedCount = 0;

        DB::transaction(function () use ($details, $scoringService, &$correctedCount, $exam) {
            foreach ($details as $detail) {
                $result = $scoringService->calculateDetailScore($detail);
                $detail->update([
                    'is_correct' => $result['is_correct'],
                    'score_earned' => $result['score'],
                ]);
                $correctedCount++;
            }

            ExamSession::where('exam_id', $exam->id)
                ->update(['is_corrected' => false]);
        });

        return $this->success([
            'corrected_count' => $correctedCount,
        ], "Reset correction completed for {$correctedCount} objective answers.");
    }

    /**
     * Get AI correction progress for an exam.
     */
    public function correctionProgress(Exam $exam)
    {
        $stats = AiCorrectionStat::where('exam_id', $exam->id)
            ->orderByDesc('created_at')
            ->get();

        if ($stats->isEmpty()) {
            return $this->success([
                'exam_id' => $exam->id,
                'stats' => [],
                'message' => 'No correction jobs found for this exam.',
            ]);
        }

        $latestStat = $stats->first();

        $questionCorrections = ExamQuestionCorrection::where('exam_id', $exam->id)
            ->whereIn('status', [CorrectionStatusEnum::PROCESSING, CorrectionStatusEnum::COMPLETED])
            ->with(['examQuestion:id,question_number,content,score_value'])
            ->orderBy('exam_question_id')
            ->get();

        $questionProgress = $questionCorrections->map(function ($qc) {
            $question = $qc->examQuestion;
            $total = $qc->total_to_correct;
            $corrected = $qc->corrected_count;
            $progressPercent = $total > 0 ? (int) round(($corrected / $total) * 100) : 0;

            return [
                'exam_question_id' => $qc->exam_question_id,
                'question_number' => $question?->question_number,
                'question_content' => $question?->content,
                'score_value' => $question?->score_value,
                'total_to_correct' => $qc->total_to_correct,
                'corrected_count' => $qc->corrected_count,
                'status' => $qc->status->value,
                'progress_percentage' => $progressPercent,
            ];
        });

        // Calculate actual completed jobs from question corrections (most accurate)
        $totalCorrectedCount = $questionCorrections->sum('corrected_count');
        $totalToCorrect = $questionCorrections->sum('total_to_correct');

        // Check if all questions are completed
        $allQuestionsCompleted = $questionCorrections->every(function ($qc) {
            return $qc->status === CorrectionStatusEnum::COMPLETED;
        });

        // Get batch info if available
        $batchInfo = null;
        $pendingJobs = null;
        if ($latestStat->batch_id) {
            $batch = Bus::findBatch($latestStat->batch_id);
            if ($batch) {
                $pendingJobs = $batch->pendingJobs;
                $batchInfo = [
                    'total_jobs' => $batch->totalJobs,
                    'processed_jobs' => $batch->processedJobs(),
                    'failed_jobs' => $batch->failedJobs,
                    'pending_jobs' => $batch->pendingJobs,
                ];
            }
        }

        // Calculate total_jobs from ALL essay answers in the exam (including unfinished sessions)
        $totalJobs = ExamResultDetail::whereHas('examSession.exam', function ($q) use ($exam) {
            $q->where('id', $exam->id);
        })->whereHas('examQuestion', function ($q) {
            $q->where('question_type', QuestionTypeEnum::ESSAY->value);
        })->count();

        // Use question_progress as source of truth for completed count
        $completedJobs = $totalCorrectedCount;

        // If we have batch info and it shows different values, prefer batch data
        if ($batchInfo) {
            // Only use batch data if it makes more sense (e.g., batch shows fewer completed than expected)
            if ($batchInfo['processed_jobs'] > 0 && $batchInfo['processed_jobs'] < $completedJobs) {
                $completedJobs = $batchInfo['processed_jobs'];
            }
        }

        // Cap progress percentage at 100%
        $progressPercentage = $totalJobs > 0 ? min(100, (int) round(($completedJobs / $totalJobs) * 100)) : 0;

        // Determine actual status
        $status = $latestStat->status;
        if ($allQuestionsCompleted && $status !== 'completed') {
            $status = 'completed';
        }

        // Estimate remaining time
        $estimatedRemainingSeconds = null;
        if ($pendingJobs !== null && $pendingJobs > 0 && $latestStat->avg_time_per_job) {
            $estimatedRemainingSeconds = (int) ceil($pendingJobs * $latestStat->avg_time_per_job);
        }

        return $this->success([
            'exam_id' => $exam->id,
            'exam_title' => $exam->title,
            'latest_correction' => [
                'id' => $latestStat->id,
                'provider' => $latestStat->provider,
                'batch_id' => $latestStat->batch_id,
                'total_jobs' => $totalJobs,
                'completed_jobs' => $completedJobs,
                'failed_jobs' => $latestStat->failed_jobs ?? 0,
                'pending_jobs' => $pendingJobs,
                'total_corrected_count' => $totalCorrectedCount,
                'total_to_correct' => $totalToCorrect,
                'avg_time_per_job' => $latestStat->avg_time_per_job,
                'status' => $status,
                'progress_percentage' => $progressPercentage,
                'estimated_remaining_seconds' => $estimatedRemainingSeconds,
                'started_at' => $latestStat->started_at,
                'finished_at' => $latestStat->finished_at,
            ],
            'question_progress' => $questionProgress,
            'all_corrections' => $stats->map(function ($stat) use ($totalJobs, $completedJobs, $latestStat) {
                // For the latest correction, use calculated values
                // For older corrections, use stored values
                $isLatest = $stat->id === $latestStat->id;

                return [
                    'id' => $stat->id,
                    'provider' => $stat->provider,
                    'total_jobs' => $isLatest ? $totalJobs : $stat->total_jobs,
                    'completed_jobs' => $isLatest ? $completedJobs : ($stat->completed_jobs ?? 0),
                    'failed_jobs' => $stat->failed_jobs ?? 0,
                    'status' => $stat->status,
                    'started_at' => $stat->started_at,
                    'finished_at' => $stat->finished_at,
                ];
            }),
        ]);
    }
}
