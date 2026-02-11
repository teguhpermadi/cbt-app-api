<?php

declare(strict_types=1);

namespace App\Http\Resources\Student;

use App\Models\ExamResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamResult
 */
final class ExamResultResource extends JsonResource
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
            'exam_session_id' => $this->exam_session_id,
            'total_score' => $this->total_score,
            'score_percent' => $this->score_percent,
            'final_score' => $this->final_score,
            'is_passed' => $this->is_passed,
            'result_type' => $this->result_type,
            'result_type_label' => $this->result_type?->label(),
            'finished_at' => $this->officialSession?->finish_time?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
