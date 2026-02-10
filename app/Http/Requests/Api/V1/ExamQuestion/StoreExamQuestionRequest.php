<?php

namespace App\Http\Requests\Api\V1\ExamQuestion;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_id' => ['required', 'ulid', 'exists:exams,id'],
            'question_id' => ['required', 'ulid', 'exists:questions,id'],
            'question_number' => ['required', 'integer', 'min:1'],
            'content' => ['required', 'string'],
            'options' => ['required', 'array'],
            'key_answer' => ['required', 'array'],
            'score_value' => ['required', 'integer', 'min:0'],
            'question_type' => ['required', new Enum(QuestionTypeEnum::class)],
            'difficulty_level' => ['required', new Enum(QuestionDifficultyLevelEnum::class)],
            'media_path' => ['nullable', 'string'],
            'hint' => ['nullable', 'string'],
        ];
    }
}
