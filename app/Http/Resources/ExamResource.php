<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 */
final class ExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'academic_year_id' => $this->academic_year_id,
            'subject_id' => $this->subject_id,
            'user_id' => $this->user_id,
            'question_bank_id' => $this->question_bank_id,
            'title' => $this->title,
            'type' => $this->type,
            'type_label' => $this->type?->label(),
            'duration' => $this->duration,
            'token' => $this->token,
            'is_token_visible' => $this->is_token_visible,
            'is_published' => $this->is_published,
            'is_randomized_question' => $this->is_randomized_question,
            'is_randomized_answer' => $this->is_randomized_answer,
            'is_show_result' => $this->is_show_result,
            'is_visible_hint' => $this->is_visible_hint,
            'max_attempts' => $this->max_attempts,
            'timer_type' => $this->timer_type,
            'timer_type_label' => $this->timer_type?->label(),
            'passing_score' => $this->passing_score,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'question_bank' => new QuestionBankResource($this->whenLoaded('questionBank')),
            'teacher' => new UserResource($this->whenLoaded('user')),
            'attempts_count' => $this->when(isset($this->attempts_count), $this->attempts_count),
            'latest_session' => $this->when(isset($this->latest_session), function () {
                return new \App\Http\Resources\Student\ExamSessionResource($this->latest_session);
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
