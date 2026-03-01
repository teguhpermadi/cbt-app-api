<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ExamCorrectionResource;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Enums\UserTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
            if (!$exam->is_show_result) {
                return $this->error('Detailed results are not available for this exam.', 403);
            }
        }

        // Ensure session belongs to exam
        if ($examSession->exam_id !== $exam->id) {
            abort(404, 'Session not found for this exam.');
        }

        $examSession->load(['user.classrooms', 'exam.subject', 'exam.classrooms']);

        $details = $examSession->examResultDetails()
            ->with(['examQuestion.originalQuestion.tags'])
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
            'is_correct' => $isCorrect ?? ($scoreEarned == $maxScore),
        ]);

        return $this->success(
            new ExamCorrectionResource($examResultDetail),
            'Correction updated successfully'
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

        DB::transaction(function () use ($validated, &$updatedCount) {
            foreach ($validated['updates'] as $updateData) {
                $detail = ExamResultDetail::with('examQuestion')->find($updateData['id']);

                if (!$detail) continue;

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
                    'is_correct' => $isCorrect ?? ($scoreEarned == $maxScore),
                ]);

                $updatedCount++;
            }
        });

        return $this->success(null, "Successfully updated {$updatedCount} answers.");
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
            if ($totalAnswered === 0) continue;

            $maxScore = max(1, (float)($question->score_value ?? 1));

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
                    if ($option->is_correct) continue;

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

                    if (!$isFunctional) {
                        $badDistractors++;
                    }

                    $distractorDetails[] = [
                        'option_id' => $option->id,
                        'chosen_all' => $chosenByAll,
                        'chosen_top' => $chosenByTop,
                        'chosen_bottom' => $chosenByBottom,
                        'is_functional' => $isFunctional
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
                    $recommendation = "Soal ini terlalu sukar dan tidak membedakan siswa (daya beda jelek). Sebaiknya dibuang atau dirombak total.";
                } elseif ($difficultyScore > 0.7) {
                    $status = 'Direvisi';
                    $recommendation = "Soal ini terlalu mudah dan daya bedanya jelek. Perlu ditambah tingkat kesukarannya.";
                } else {
                    $status = 'Direvisi';
                    $recommendation = "Tingkat kesukaran sedang, tetapi daya bedanya jelek (mungkin membingungkan siswa pandai). Perlu perbaikan pada redaksi soal atau pengecoh.";
                }
            } elseif ($discriminationScore < 0.3) {
                $status = 'Direvisi';
                $recommendation = "Daya beda soal ini {$discriminationCategory}. Soal dapat diterima dengan sedikit perbaikan kalimat.";
            }

            if ($distractorStatus === 'Kurang Berfungsi') {
                $status = ($status === 'Diterima') ? 'Direvisi' : $status;
                $recommendation .= " Beberapa pengecoh tidak berfungsi dengan baik (kurang diminati atau lebih mengecoh kelompok atas).";
            }

            $statusCounts[$status]++;

            $analysisData[] = [
                'question_id' => $question->id,
                'question_number' => $question->question_number,
                'question_type' => $question->question_type,
                'content' => $question->content,
                'difficulty' => [
                    'score' => round($difficultyScore, 2),
                    'category' => $difficultyCategory
                ],
                'discrimination' => [
                    'score' => round($discriminationScore, 2),
                    'category' => $discriminationCategory
                ],
                'distractor' => [
                    'status' => $distractorStatus,
                    'details' => $distractorDetails ?? []
                ],
                'conclusion' => [
                    'status' => $status,
                    'recommendation' => $recommendation
                ]
            ];
        }

        $totalExamQuestions = $questions->count();
        $acceptedPercent = $totalExamQuestions > 0 ? round(($statusCounts['Diterima'] / $totalExamQuestions) * 100) : 0;

        $overallRecommendation = "Instrumen tes memerlukan banyak perbaikan.";
        if ($acceptedPercent >= 80) {
            $overallRecommendation = "Secara umum, instrumen tes sudah sangat baik dan layak diujikan kembali.";
        } elseif ($acceptedPercent >= 50) {
            $overallRecommendation = "Instrumen tes cukup baik, namun beberapa soal perlu direvisi sebelum digunakan kembali.";
        } else {
            $overallRecommendation = "Sebagian besar butir soal kurang relevan atau membingungkan. Sangat disarankan untuk merombak ulang instrumen tes.";
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
            'item_analysis' => $analysisData
        ]);
    }
}
