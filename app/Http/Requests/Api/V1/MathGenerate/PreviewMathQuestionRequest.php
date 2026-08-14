<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MathGenerate;

use Illuminate\Foundation\Http\FormRequest;

class PreviewMathQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $domains = array_keys(config('mathengine.domains', []));

        return [
            'domain' => ['required', 'string', 'in:'.implode(',', $domains)],
            'operation' => ['nullable', 'string'],
            'level' => ['required', 'integer', 'min:1', 'max:7'],
            'number_type' => ['nullable', 'string', 'in:natural,whole,integer,rational,real'],
            'count' => ['required', 'integer', 'min:1', 'max:50'],
            'seed' => ['nullable', 'integer', 'min:0'],
            'with_story' => ['nullable', 'boolean'],
            'with_distractors' => ['nullable', 'boolean'],
            'distractor_count' => ['nullable', 'integer', 'min:2', 'max:4'],
            'operand_count' => ['nullable', 'integer', 'min:2', 'max:6'],
            'shape' => ['nullable', 'string'],
            'theme' => ['nullable', 'string', 'max:100'],
            'score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'timer' => ['nullable', 'integer', 'min:5', 'max:900'],
            'hint' => ['nullable', 'string', 'max:500'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.required' => 'Domain wajib diisi.',
            'domain.in' => 'Domain tidak valid.',
            'level.required' => 'Level wajib diisi.',
            'level.min' => 'Level minimal 1.',
            'level.max' => 'Level maksimal 7.',
            'count.required' => 'Jumlah soal wajib diisi.',
            'count.min' => 'Jumlah soal minimal 1.',
            'count.max' => 'Jumlah soal maksimal 50.',
        ];
    }
}
