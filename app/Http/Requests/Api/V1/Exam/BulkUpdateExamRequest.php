<?php

namespace App\Http\Requests\Api\V1\Exam;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exams' => ['required', 'array'],
            'exams.*.id' => ['required', 'ulid', 'exists:exams,id'],
            'exams.*.title' => ['sometimes', 'string', 'max:255'],
            'exams.*.is_published' => ['sometimes', 'boolean'],
            // Add other fields as needed for bulk updates
        ];
    }
}
