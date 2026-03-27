<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLearningPathRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'ulid'],
            'classroom_id' => ['required', 'ulid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_published' => ['boolean'],
        ];
    }
}
