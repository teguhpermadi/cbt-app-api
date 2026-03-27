<?php

declare(strict_types=1);

namespace App\Http\Requests\Learning;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLearningUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'learning_path_id' => ['required', 'ulid'],
            'title' => ['required', 'string', 'max:255'],
            'xp_reward' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
