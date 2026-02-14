<?php

declare(strict_types=1);

namespace App\Http\Resources\Student;

use App\Http\Resources\ExamResource;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamSession
 */
final class ExamSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'user_id' => $this->user_id,
            'attempt_number' => $this->attempt_number,
            'total_score' => $this->total_score,
            'total_max_score' => $this->total_max_score,
            'final_score' => $this->final_score,
            'is_finished' => $this->is_finished,
            'is_corrected' => $this->is_corrected,
            'start_time' => $this->start_time?->toIso8601String(),
            'finish_time' => $this->finish_time?->toIso8601String(),
            'duration_taken' => $this->duration_taken,
            'ip_address' => $this->ip_address,
            'exam' => new ExamResource($this->whenLoaded('exam')),
            'student' => new \App\Http\Resources\UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
