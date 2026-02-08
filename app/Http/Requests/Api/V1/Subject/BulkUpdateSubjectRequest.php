<?php

namespace App\Http\Requests\Api\V1\Subject;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateSubjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subjects' => ['required', 'array'],
            'subjects.*.id' => [
                'required',
                'ulid',
                Rule::exists('subjects', 'id'),
            ],
            'subjects.*.name' => ['sometimes', 'string', 'max:255'],
            'subjects.*.code' => ['sometimes', 'string', 'max:50'],
            'subjects.*.description' => ['sometimes', 'nullable', 'string'],
            'subjects.*.user_id' => ['sometimes', 'ulid', 'exists:users,id'],
            'subjects.*.academic_year_id' => ['sometimes', 'ulid', 'exists:academic_years,id'],
            'subjects.*.classroom_id' => ['sometimes', 'ulid', 'exists:classrooms,id'],
        ];
    }
}
