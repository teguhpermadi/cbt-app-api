<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Curriculum;

use Illuminate\Foundation\Http\FormRequest;

final class StoreCurriculumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:curricula,code'],
            'description' => ['nullable', 'string'],
            'phase' => ['required', 'string', 'max:50'],
            'level' => ['required', 'string', 'in:SD,SMP,SMA,SMK'],
            'grade_range' => ['required', 'array'],
            'grade_range.min' => ['required', 'integer', 'min:1', 'max:12'],
            'grade_range.max' => ['required', 'integer', 'min:1', 'max:12', 'gte:grade_range.min'],
            'academic_year' => ['required', 'string', 'max:20'],
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

    public function messages(): array
    {
        return [
            'grade_range.max.gte' => 'Grade range max must be greater than or equal to min.',
        ];
    }
}
