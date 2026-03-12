<?php

namespace App\Http\Requests\Api\V1\ReadingMaterial;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'question_bank_id' => ['nullable', 'ulid', 'exists:question_banks,id'],
        ];
    }
}
