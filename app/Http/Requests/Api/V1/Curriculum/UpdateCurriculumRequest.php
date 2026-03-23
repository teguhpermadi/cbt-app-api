<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Curriculum;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $curriculumId = $this->route('curriculum');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'phase' => ['sometimes', 'required', 'string', 'max:50'],
            'level' => ['sometimes', 'required', 'string', 'in:SD,SMP,SMA,SMK'],
            'grade_range' => ['sometimes', 'required', 'array'],
            'grade_range.min' => ['required_with:grade_range', 'integer', 'min:1', 'max:12'],
            'grade_range.max' => ['required_with:grade_range', 'integer', 'min:1', 'max:12', 'gte:grade_range.min'],
            'academic_year' => ['sometimes', 'required', 'string', 'max:20'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.name' => ['required_with:subjects', 'string', 'max:255'],
            'subjects.*.code' => ['required_with:subjects', 'string', 'max:50'],
            'subjects.*.description' => ['nullable', 'string'],
            'subjects.*.learning_outcomes' => ['nullable', 'array'],
            'subjects.*.learning_outcomes.*.code' => ['required_with:subjects.*.learning_outcomes', 'string', 'max:50'],
            'subjects.*.learning_outcomes.*.description' => ['required_with:subjects.*.learning_outcomes', 'string'],
            'subjects.*.learning_outcomes.*.order' => ['nullable', 'integer', 'min:1'],
            'subjects.*.learning_objectives' => ['nullable', 'array'],
            'subjects.*.learning_objectives.*.code' => ['required_with:subjects.*.learning_objectives', 'string', 'max:50'],
            'subjects.*.learning_objectives.*.description' => ['required_with:subjects.*.learning_objectives', 'string'],
            'subjects.*.learning_objectives.*.order' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
