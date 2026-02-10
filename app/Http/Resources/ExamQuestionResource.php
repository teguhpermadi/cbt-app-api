<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExamQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamQuestion
 */
final class ExamQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'question_id' => $this->question_id,
            'question_number' => $this->question_number,
            'content' => $this->content,
            'options' => $this->options,
            'key_answer' => $this->key_answer,
            'score_value' => $this->score_value,
            'question_type' => $this->question_type,
            'question_type_label' => $this->question_type?->label(),
            'difficulty_level' => $this->difficulty_level,
            'difficulty_level_label' => $this->difficulty_level?->getLabel(),
            'media_path' => $this->media_path,
            'hint' => $this->hint,
            'exam' => new ExamResource($this->whenLoaded('exam')),
            'original_question' => new QuestionResource($this->whenLoaded('originalQuestion')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
