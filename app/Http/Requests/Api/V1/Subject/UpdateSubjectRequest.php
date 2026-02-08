<?php

namespace App\Http\Requests\Api\V1\Subject;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubjectRequest extends FormRequest
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
        $subjectId = $this->route('subject');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:subjects,code,' . $subjectId . ',id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image_url' => ['sometimes', 'nullable', 'string', 'url'],
            'logo_url' => ['sometimes', 'nullable', 'string', 'url'],
            'user_id' => ['sometimes', 'ulid', 'exists:users,id'],
            'color' => ['sometimes', 'nullable', 'string', 'max:20'],
            'class_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'academic_year_id' => ['sometimes', 'ulid', 'exists:academic_years,id'],
            'classroom_id' => ['sometimes', 'ulid', 'exists:classrooms,id'],
        ];
    }
}
