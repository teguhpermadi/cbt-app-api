<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subject
 */
final class SubjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'logo_url' => $this->logo_url,
            'color' => $this->color,
            'class_name' => $this->class_name,
            'user_id' => $this->user_id,
            'academic_year_id' => $this->academic_year_id,
            'classroom_id' => $this->classroom_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'classroom' => new ClassroomResource($this->whenLoaded('classroom')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
