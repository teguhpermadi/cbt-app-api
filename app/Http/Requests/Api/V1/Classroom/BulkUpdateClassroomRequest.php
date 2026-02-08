<?php

namespace App\Http\Requests\Api\V1\Classroom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateClassroomRequest extends FormRequest
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
            'classrooms' => ['required', 'array'],
            'classrooms.*.id' => [
                'required',
                'ulid',
                Rule::exists('classrooms', 'id'),
            ],
            'classrooms.*.name' => ['sometimes', 'string', 'max:255'],
            'classrooms.*.code' => ['sometimes', 'string', 'max:50'],
            'classrooms.*.level' => ['sometimes', 'string', 'max:50'],
            'classrooms.*.user_id' => ['sometimes', 'nullable', 'ulid', 'exists:users,id'],
            'classrooms.*.academic_year_id' => ['sometimes', 'ulid', 'exists:academic_years,id'],
        ];
    }
}
