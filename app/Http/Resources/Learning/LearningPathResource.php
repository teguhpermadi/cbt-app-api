<?php

declare(strict_types=1);

namespace App\Http\Resources\Learning;

use App\Http\Resources\ClassroomResource;
use App\Http\Resources\SubjectResource;
use App\Http\Resources\UserResource;
use App\Models\LearningPath;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LearningPath
 */
final class LearningPathResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'classroom_id' => $this->classroom_id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'description' => $this->description,
            'order' => $this->order,
            'is_published' => $this->is_published,
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'user' => new UserResource($this->whenLoaded('user')),
            'units' => LearningUnitResource::collection($this->whenLoaded('units')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
