<?php

declare(strict_types=1);

namespace App\Http\Resources\Student;

use App\Models\ExamResultDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamResultDetail
 */
final class ExamResultDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_session_id' => $this->exam_session_id,
            'exam_question_id' => $this->exam_question_id,
            'student_answer' => $this->student_answer,
            'is_correct' => $this->is_correct,
            'score_earned' => $this->score_earned,
            'correction_notes' => $this->correction_notes,
            'answered_at' => $this->answered_at?->toIso8601String(),
            'time_spent' => $this->time_spent,
            'question_number' => $this->question_number,
            'is_flagged' => $this->is_flagged,
            'exam_question' => new ExamQuestionResource($this->whenLoaded('examQuestion')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
