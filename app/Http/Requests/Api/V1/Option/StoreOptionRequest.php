<?php

namespace App\Http\Requests\Api\V1\Option;

use App\Enums\QuestionTypeEnum;
use App\Models\Question;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOptionRequest extends FormRequest
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
        $questionId = $this->input('question_id');
        $question = null;

        if ($questionId) {
            $question = Question::find($questionId);
        }

        $rules = [
            'question_id' => ['required', 'string', 'exists:questions,id'],
            'option_key' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string'],
            'order' => ['nullable', 'integer'],
            'is_correct' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];

        if ($question) {
            switch ($question->type) {
                case QuestionTypeEnum::TRUE_FALSE:
                    $rules['option_key'] = ['required', 'string', Rule::in(['T', 'F'])];
                    break;
                case QuestionTypeEnum::MATCHING:
                    $rules['metadata'] = ['required', 'array'];
                    $rules['metadata.side'] = ['required', 'string', Rule::in(['left', 'right'])];
                    $rules['metadata.pair_id'] = ['required', 'integer'];
                    $rules['metadata.match_with'] = ['required', 'string'];
                    break;
                case QuestionTypeEnum::SEQUENCE:
                    $rules['metadata'] = ['required', 'array'];
                    $rules['metadata.correct_position'] = ['required', 'integer'];
                    break;
            }
        }

        return $rules;
    }
}
