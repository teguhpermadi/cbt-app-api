<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLearningLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learning_unit_id' => ['required', 'ulid'],
            'question_bank_id' => ['nullable', 'ulid'],
            'title' => ['required', 'string', 'max:255'],
            'content_type' => ['required', Rule::enum(LearningContentTypeEnum::class)],
            'content_data' => ['nullable', 'array'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
            'is_published' => ['boolean'],
        ];
    }
}
