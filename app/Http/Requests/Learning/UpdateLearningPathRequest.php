<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLearningPathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['ulid'],
            'classroom_id' => ['ulid'],
            'title' => ['string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'order' => ['integer'],
        ];
    }
}
