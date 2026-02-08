<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Enums\UserTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteTeacherRequest extends FormRequest
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
            'ids' => ['required', 'array'],
            'ids.*' => [
                'required',
                'ulid',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('user_type', UserTypeEnum::TEACHER);
                }),
            ],
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
