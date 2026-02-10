<?php

namespace App\Http\Requests\Api\V1\ReadingMaterial;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReadingMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ];
    }
}
