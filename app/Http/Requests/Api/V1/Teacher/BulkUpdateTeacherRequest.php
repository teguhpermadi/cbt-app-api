<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateTeacherRequest extends FormRequest
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
            'teachers' => ['required', 'array'],
            'teachers.*.id' => [
                'required',
                'ulid',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('user_type', UserTypeEnum::TEACHER);
                }),
            ],
            'teachers.*.name' => ['sometimes', 'string', 'max:255'],
            'teachers.*.username' => ['sometimes', 'string', 'max:255'],
            'teachers.*.email' => ['sometimes', 'string', 'email', 'max:255'],
            'teachers.*.password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
