<?php

declare(strict_types=1);

namespace App\Http\Resources\Learning;

use App\Http\Resources\QuestionBankResource;
use App\Models\LearningLesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningLesson
 */
final class LearningLessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'learning_unit_id' => $this->learning_unit_id,
            'question_bank_id' => $this->question_bank_id,
            'title' => $this->title,
            'content_type' => $this->content_type,
            'content_data' => $this->content_data,
            'order' => $this->order,
            'xp_reward' => $this->xp_reward,
            'is_published' => $this->is_published,
            'unit' => new LearningUnitResource($this->whenLoaded('unit')),
            'question_bank' => new QuestionBankResource($this->whenLoaded('questionBank')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
