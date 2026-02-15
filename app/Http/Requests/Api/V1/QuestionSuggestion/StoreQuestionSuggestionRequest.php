<?php

namespace App\Http\Requests\Api\V1\QuestionSuggestion;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionSuggestionRequest extends FormRequest
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
            'question_id' => ['required', 'string', 'exists:questions,id'],
            'description' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ];
    }
}
