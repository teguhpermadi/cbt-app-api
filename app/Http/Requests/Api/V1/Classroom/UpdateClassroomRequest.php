<?php

namespace App\Http\Requests\Api\V1\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClassroomRequest extends FormRequest
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
        $classroomId = $this->route('classroom');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:classrooms,code,' . $classroomId . ',id'],
            'level' => ['sometimes', 'string', 'max:50'],
            'user_id' => ['sometimes', 'nullable', 'ulid', 'exists:users,id'],
            'academic_year_id' => ['sometimes', 'ulid', 'exists:academic_years,id'],
        ];
    }
}
