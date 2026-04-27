<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\QuestionBankReviewer;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateQuestionBankReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
            'suggested_questions_count' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.min' => 'Rating must be at least 1',
            'rating.max' => 'Rating must be at most 5',
            'suggested_questions_count.min' => 'Suggested questions count must be at least 0',
        ];
    }
}
