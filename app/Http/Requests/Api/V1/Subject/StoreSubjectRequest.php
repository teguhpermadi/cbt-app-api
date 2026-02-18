<?php

namespace App\Http\Requests\Api\V1\Subject;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:subjects,code'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'string', 'url'],
            'logo_url' => ['nullable', 'string', 'url'],
            'user_id' => ['required', 'ulid', 'exists:users,id'],
            'color' => ['nullable', 'string', 'max:20'],
            'class_name' => ['nullable', 'string', 'max:100'],
            'academic_year_id' => ['required', 'ulid', 'exists:academic_years,id'],
            'classroom_id' => ['required', 'ulid', 'exists:classrooms,id'],
        ];
    }
}
