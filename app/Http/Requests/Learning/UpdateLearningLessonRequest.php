<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use App\Enums\LearningContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLearningLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learning_unit_id' => ['ulid'],
            'question_bank_id' => ['nullable', 'ulid'],
            'title' => ['string', 'max:255'],
            'content_type' => [Rule::enum(LearningContentType::class)],
            'content_data' => ['nullable', 'array'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
            'order' => ['integer'],
            'is_published' => ['boolean'],
        ];
    }
}
