<?php

namespace App\Http\Requests\Api\V1\Exam;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'ulid', 'exists:exams,id'],
            'force' => ['boolean'],
        ];
    }
}
