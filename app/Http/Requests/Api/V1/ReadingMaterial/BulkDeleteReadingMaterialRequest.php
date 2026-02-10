<?php

namespace App\Http\Requests\Api\V1\ReadingMaterial;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteReadingMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'string', 'exists:reading_materials,id'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}
