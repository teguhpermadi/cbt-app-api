<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(\Illuminate\Http\Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user') ? new UserResource($this->user) : null,
            'reading_material' => $this->whenLoaded('readingMaterial') ? $this->readingMaterial : null, // Assuming ReadingMaterialResource doesn't exist or isn't needed yet
            'type' => $this->type,
            'difficulty' => $this->difficulty,
            'timer' => $this->timer,
            'content' => $this->content,
            'score' => $this->score,
            'hint' => $this->hint,
            'order' => $this->order,
            'is_approved' => $this->is_approved,
            'tags' => $this->tags->pluck('name'),
            'media' => [
                'content' => $this->getMedia('question_content')->map(fn($media) => [
                    'id' => $media->ulid ?? $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'url' => $media->getFullUrl(),
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ]),
            ],
            'options' => $this->whenLoaded('options'), // Usually we would use OptionResource if it exists
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
