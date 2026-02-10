<?php

namespace App\Http\Requests\Api\V1\ExamQuestion;

use App\Enums\QuestionDifficultyLevelEnum;
use App\Enums\QuestionTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_id' => ['sometimes', 'ulid', 'exists:exams,id'],
            'question_id' => ['sometimes', 'ulid', 'exists:questions,id'],
            'question_number' => ['sometimes', 'integer', 'min:1'],
            'content' => ['sometimes', 'string'],
            'options' => ['sometimes', 'array'],
            'key_answer' => ['sometimes', 'array'],
            'score_value' => ['sometimes', 'integer', 'min:0'],
            'question_type' => ['sometimes', new Enum(QuestionTypeEnum::class)],
            'difficulty_level' => ['sometimes', new Enum(QuestionDifficultyLevelEnum::class)],
            'media_path' => ['nullable', 'string'],
            'hint' => ['nullable', 'string'],
        ];
    }
}
