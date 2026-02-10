<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OptionResource extends JsonResource
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
            'question_id' => $this->question_id,
            'option_key' => $this->option_key,
            'content' => $this->content,
            'order' => $this->order,
            'is_correct' => $this->is_correct,
            'metadata' => $this->metadata,
            'media' => [
                'option_media' => $this->getMedia('option_media')->map(fn($media) => [
                    'id' => $media->ulid ?? $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'url' => $media->getFullUrl(),
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ]),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
