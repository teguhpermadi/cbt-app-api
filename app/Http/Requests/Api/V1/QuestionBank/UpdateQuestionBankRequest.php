<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\QuestionBank;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateQuestionBankRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subject_id' => ['sometimes', 'required', 'exists:subjects,id'],
            'user_id' => ['nullable', 'string', 'exists:users,id'],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
