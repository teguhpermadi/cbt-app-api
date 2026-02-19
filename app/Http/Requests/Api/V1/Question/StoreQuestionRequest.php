<?php

namespace App\Http\Requests\Api\V1\Question;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
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
            'question_bank_id' => ['nullable', 'string', 'exists:question_banks,id'],
            'reading_material_id' => ['nullable', 'string', 'exists:reading_materials,id'],
            'type' => ['required', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionTypeEnum::class)],
            'difficulty' => ['required', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionDifficultyLevelEnum::class)],
            'timer' => ['required', 'integer', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionTimeEnum::class)],
            'content' => ['required', 'string'],
            'score' => ['required', 'integer', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionScoreEnum::class)],
            'hint' => ['nullable', 'string'],
            'order' => ['nullable', 'integer'],
            'is_approved' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ];
    }
}
