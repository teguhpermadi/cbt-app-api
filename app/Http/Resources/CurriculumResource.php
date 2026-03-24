<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Curriculum
 */
final class CurriculumResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->_id,
            'name' => $this->name,
            'code' => $this->code,
            'curriculum_type' => $this->curriculum_type,
            'description' => $this->description,
            'phase' => $this->phase,
            'level' => $this->level,
            'grade_range' => $this->grade_range,
            'academic_year' => $this->academic_year,
            'subjects' => $this->subjects ?? [],
            'is_active' => $this->is_active ?? true,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
