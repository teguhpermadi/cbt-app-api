<?php

namespace App\Http\Requests\Api\V1\Question;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
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
            'reading_material_id' => ['nullable', 'string', 'exists:reading_materials,id'],
            'type' => ['sometimes', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionTypeEnum::class)],
            'difficulty' => ['sometimes', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionDifficultyLevelEnum::class)],
            'timer' => ['sometimes', 'integer', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionTimeEnum::class)],
            'content' => ['sometimes', 'string'],
            'score' => ['sometimes', 'integer', \Illuminate\Validation\Rule::enum(\App\Enums\QuestionScoreEnum::class)],
            'hint' => ['nullable', 'string'],
            'order' => ['nullable', 'integer'],
            'is_approved' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
        ];
    }
}
