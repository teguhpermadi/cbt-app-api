<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateStudentRequest extends FormRequest
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
            'students' => ['required', 'array'],
            'students.*.id' => [
                'required',
                'ulid',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('user_type', UserTypeEnum::STUDENT);
                }),
            ],
            'students.*.name' => ['sometimes', 'string', 'max:255'],
            'students.*.username' => ['sometimes', 'string', 'max:255'],
            'students.*.email' => ['sometimes', 'string', 'email', 'max:255'],
            'students.*.password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
