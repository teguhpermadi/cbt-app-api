<?php

namespace App\Http\Requests\Api\V1\ExamQuestion;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'ulid', 'exists:exam_questions,id'],
        ];
    }
}
