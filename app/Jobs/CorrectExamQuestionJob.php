<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CorrectionStatusEnum;
use App\Enums\QuestionTypeEnum;
use App\Models\ExamQuestionCorrection;
use App\Models\ExamResult;
use App\Models\ExamResultDetail;
use App\Models\ExamSession;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use romanzipp\QueueMonitor\Traits\IsMonitored;

final class CorrectExamQuestionJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, IsMonitored, Queueable, SerializesModels;

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
        public ExamResultDetail $examResultDetail,
        public ?string $triggeredBy = null
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

        $queueDataPayload = [
            'description' => "Koreksi ujian '{$exam->title}' siswa {$studentName} soal no {$question->question_number}"
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

            Log::info("AI Correction (Empty Answer) - Student: {$studentName}, Question: ".strip_tags($question->content).", Score: 0/{$question->score_value}");

            return;
        }

        $keyAnswer = is_array($question->key_answer)
            ? json_encode($question->key_answer, JSON_UNESCAPED_UNICODE)
            : $question->key_answer;

        $maxScore = $question->score_value;

        try {
            // Using gemini-2.0-flash as the latest available model in Prism for Gemini
            $response = Prism::structured()
                ->using(Provider::Gemini, 'gemini-2.0-flash')
                ->withSystemPrompt('Kamu adalah asisten guru pakar yang bertugas mengoreksi jawaban siswa secara adil dan akurat.')
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
                        new NumberSchema('score', 'The score earned by the student (0 to '.$maxScore.')'),
                        new StringSchema('notes', 'Brief feedback or explanation for the score'),
                    ],
                    ['score', 'notes']
                ))
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
                $detail->update([
                    'score_earned' => $aiScore,
                    'is_correct' => ($aiScore >= ($maxScore / 2)),
                    'correction_notes' => $aiNotes,
                ]);

                $this->updateQuestionCorrectionProgress($question->exam_id, $question->id);

                $this->updateSessionTotals($session);

                Log::info('AI Correction Success', [
                    'student_name' => $studentName,
                    'question' => strip_tags($question->content),
                    'student_answer' => $studentAnswer,
                    'max_score' => $maxScore,
                    'earned_score' => $aiScore,
                    'notes' => $aiNotes,
                ]);
            });
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'rate limit')) {
                $this->release(30);

                return;
            }
            Log::error("AI Correction failed for Detail ID: {$detail->id}. Error: ".$e->getMessage());
            throw $e;
        }
    }

    protected function updateSessionTotals(ExamSession $session)
    {
        $session->load('examResultDetails.examQuestion');

        $totalEarnedScore = $session->examResultDetails->sum('score_earned');
        $totalMaxScore = $session->examResultDetails->sum(function ($d) {
            return $d->examQuestion->score_value ?? 0;
        });

        $hasEssayOrShortAnswer = $this->hasEssayOrShortAnswerQuestions($session);

        $session->update([
            'total_score' => $totalEarnedScore,
            'total_max_score' => $totalMaxScore,
            'is_corrected' => ! $hasEssayOrShortAnswer,
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

    protected function hasEssayOrShortAnswerQuestions(ExamSession $session): bool
    {
        return $session->examResultDetails()
            ->whereHas('examQuestion', function ($q) {
                $q->whereIn('question_type', [
                    QuestionTypeEnum::SHORT_ANSWER->value,
                    QuestionTypeEnum::ESSAY->value,
                    QuestionTypeEnum::MATH_INPUT->value,
                    QuestionTypeEnum::ARABIC_RESPONSE->value,
                    QuestionTypeEnum::JAVANESE_RESPONSE->value,
                ]);
            })
            ->exists();
    }
}
