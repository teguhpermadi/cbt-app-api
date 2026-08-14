<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MathGenerate;

use Illuminate\Foundation\Http\FormRequest;

class BatchPreviewMathQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $domains = array_keys(config('mathengine.domains', []));

        return [
            'requirements' => ['required', 'array', 'min:1', 'max:20'],
            'requirements.*.domain' => ['required', 'string', 'in:'.implode(',', $domains)],
            'requirements.*.operation' => ['nullable', 'string'],
            'requirements.*.level' => ['required', 'integer', 'min:1', 'max:7'],
            'requirements.*.number_type' => ['nullable', 'string', 'in:natural,whole,integer,rational,real'],
            'requirements.*.count' => ['required', 'integer', 'min:1', 'max:20'],
            'requirements.*.shape' => ['nullable', 'string'],
            'requirements.*.with_story' => ['nullable', 'boolean'],
            'master_seed' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'requirements.required' => 'Requirements wajib diisi.',
            'requirements.min' => 'Minimal 1 requirement.',
            'requirements.max' => 'Maksimal 20 requirement per batch.',
        ];
    }
}
