<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExamReadingMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamReadingMaterial
 */
final class ExamReadingMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'reading_material_id' => $this->reading_material_id,
            'title' => $this->title,
            'content' => $this->content,
            'media_path' => $this->media_path,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
