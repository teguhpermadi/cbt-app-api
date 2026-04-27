<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class QuestionBankReviewerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_bank_id' => $this->question_bank_id,
            'question_bank' => new QuestionBankResource($this->whenLoaded('questionBank')),
            'user' => new UserResource($this->whenLoaded('user')),
            'state' => $this->state,
            'state_label' => $this->state?->label(),
            'state_color' => $this->state?->color(),
            'rating' => $this->rating,
            'notes' => $this->notes,
            'suggested_questions_count' => $this->suggested_questions_count,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
