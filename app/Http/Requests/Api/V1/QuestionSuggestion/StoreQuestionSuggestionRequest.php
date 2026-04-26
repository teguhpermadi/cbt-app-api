<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\QuestionSuggestion;

use Illuminate\Foundation\Http\FormRequest;

final class StoreQuestionSuggestionRequest extends FormRequest
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
            'data.content' => ['sometimes', 'string'],
            'data.type' => ['sometimes', 'string'],
            'data.difficulty' => ['sometimes', 'string'],
            'data.timer' => ['sometimes', 'integer'],
            'data.score' => ['sometimes', 'integer'],
            'data.hint' => ['nullable', 'string'],
            'data.is_approved' => ['sometimes', 'boolean'],
            'data.reading_material_id' => ['nullable', 'string'],
            'data.options' => ['sometimes', 'array'],
            'data.options.update' => ['sometimes', 'array'],
            'data.options.update.*.id' => ['required', 'string'],
            'data.options.update.*.content' => ['sometimes', 'string'],
            'data.options.update.*.order' => ['sometimes', 'integer'],
            'data.options.update.*.is_correct' => ['sometimes', 'boolean'],
            'data.options.update.*.metadata' => ['sometimes', 'array'],
            'data.options.create' => ['sometimes', 'array'],
            'data.options.create.*.content' => ['required', 'string'],
            'data.options.create.*.option_key' => ['nullable', 'string'],
            'data.options.create.*.order' => ['nullable', 'integer'],
            'data.options.create.*.is_correct' => ['nullable', 'boolean'],
            'data.options.create.*.metadata' => ['nullable', 'array'],
            'data.options.delete' => ['sometimes', 'array'],
            'data.options.delete.*' => ['required', 'string'],
        ];
    }
}
