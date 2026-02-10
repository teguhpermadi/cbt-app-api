<?php

namespace App\Http\Requests\Api\V1\ExamQuestion;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exam_questions' => ['required', 'array'],
            'exam_questions.*.id' => ['required', 'ulid', 'exists:exam_questions,id'],
            'exam_questions.*.question_number' => ['sometimes', 'integer', 'min:1'],
            'exam_questions.*.score_value' => ['sometimes', 'integer', 'min:0'],
            'exam_questions.*.content' => ['sometimes', 'string'],
            'exam_questions.*.options' => ['sometimes', 'array'],
            'exam_questions.*.key_answer' => ['sometimes', 'array'],
            'exam_questions.*.hint' => ['sometimes', 'string'],
        ];
    }
}
