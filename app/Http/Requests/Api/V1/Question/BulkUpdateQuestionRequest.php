<?php

namespace App\Http\Requests\Api\V1\Question;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateQuestionRequest extends FormRequest
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
            'questions' => ['required', 'array'],
            'questions.*.id' => ['required', 'string', 'exists:questions,id'],
            'questions.*.difficulty' => ['sometimes', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionDifficultyLevelEnum::class)],
            'questions.*.timer' => ['sometimes', 'integer', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionTimeEnum::class)],
            'questions.*.score' => ['sometimes', 'integer', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionScoreEnum::class)],
            'questions.*.order' => ['nullable', 'integer'],
            'questions.*.is_approved' => ['nullable', 'boolean'],
        ];
    }
}
