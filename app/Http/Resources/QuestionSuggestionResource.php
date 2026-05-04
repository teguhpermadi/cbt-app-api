<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\ReadingMaterialResource;
use App\Http\Resources\OptionResource;

class QuestionSuggestionResource extends JsonResource
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
            'question' => $this->whenLoaded('question') ? new QuestionResource($this->question) : null,
            'user' => $this->whenLoaded('user') ? new UserResource($this->user) : null,

            // Suggestion details
            'data' => $this->data,
            'description' => $this->description,
            'state' => $this->state,
            'state_label' => $this->state?->label(),
            'state_color' => $this->state?->color(),

            // Aligned fields with QuestionResource (Proposed values)
            'question_bank_id' => $this->data['question_bank_id'] ?? ($this->question?->question_bank_id ?? $this->question?->questionBanks->first()?->id),
            'reading_material_id' => $this->data['reading_material_id'] ?? $this->question?->reading_material_id,
            'reading_material' => $this->whenLoaded('question.readingMaterial') ? new ReadingMaterialResource($this->question->readingMaterial) : null,
            'type' => $this->data['type'] ?? $this->question?->type,
            'difficulty' => $this->data['difficulty'] ?? $this->question?->difficulty,
            'timer' => $this->data['timer'] ?? $this->question?->timer,
            'content' => $this->data['content'] ?? $this->question?->content,
            'score' => $this->data['score'] ?? $this->question?->score,
            'hint' => $this->data['hint'] ?? $this->question?->hint,
            'order' => $this->data['order'] ?? $this->question?->order,

            'tags' => $this->data['tags'] ?? ($this->question?->tags ? $this->question->tags->pluck('name') : []),

            'media' => [
                'content' => $this->question?->getMedia('question_content')->map(fn($media) => [
                    'id' => $media->ulid ?? $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'url' => $media->getFullUrl(),
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                ]) ?? [],
            ],

            'options' => $this->whenLoaded('question.options') ? OptionResource::collection($this->question->options) : [],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
