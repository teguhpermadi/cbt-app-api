<?php

declare(strict_types=1);

namespace App\Http\Resources\Learning;

use App\Models\LearningUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningUnit
 */
final class LearningUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'learning_path_id' => $this->learning_path_id,
            'title' => $this->title,
            'order' => $this->order,
            'xp_reward' => $this->xp_reward,
            'is_published' => $this->is_published,
            'learning_path' => new LearningPathResource($this->whenLoaded('learningPath')),
            'lessons' => LearningLessonResource::collection($this->whenLoaded('lessons')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
