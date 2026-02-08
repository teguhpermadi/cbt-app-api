<?php

namespace App\Http\Requests\Api\V1\Classroom;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', 'unique:classrooms,code'],
            'level' => ['required', 'string', 'max:50'],
            'user_id' => ['nullable', 'ulid', 'exists:users,id'],
            'academic_year_id' => ['required', 'ulid', 'exists:academic_years,id'],
        ];
    }
}
