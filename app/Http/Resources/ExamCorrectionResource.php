<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamCorrectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_session_id' => $this->exam_session_id,
            'question_number' => $this->question_number,
            'question_type' => $this->examQuestion->question_type,
            'question_content' => $this->examQuestion->content,
            'student_answer' => $this->student_answer,
            'key_answer' => $this->examQuestion->key_answer,
            'score_earned' => $this->score_earned,
            'max_score' => $this->examQuestion->score_value,
            'is_correct' => $this->is_correct, // null, true, or false
            'correction_notes' => $this->correction_notes,
            'answered_at' => $this->answered_at,
            'options' => $this->examQuestion->options, // Context for MC/Multiple Answer
        ];
    }
}
