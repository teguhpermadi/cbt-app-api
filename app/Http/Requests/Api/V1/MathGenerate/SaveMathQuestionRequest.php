<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\MathGenerate;

use Illuminate\Foundation\Http\FormRequest;

class SaveMathQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_bank_id' => ['required', 'string', 'exists:question_banks,id'],
            'previews' => ['required', 'array', 'min:1', 'max:50'],
            'previews.*.temp_id' => ['required', 'string'],
            'previews.*.content' => ['required', 'string', 'max:5000'],
            'previews.*.content_latex' => ['nullable', 'string', 'max:2000'],
            'previews.*.options' => ['required', 'array', 'min:2', 'max:5'],
            'previews.*.options.*.key' => ['required', 'string', 'max:10'],
            'previews.*.options.*.content' => ['required', 'string', 'max:1000'],
            'previews.*.options.*.is_correct' => ['required', 'boolean'],
            'previews.*.correct_answer' => ['required', 'string'],
            'previews.*.correct_answer_latex' => ['nullable', 'string'],
            'previews.*.difficulty' => ['nullable', 'string', 'in:mudah,sedang,sulit'],
            'previews.*.score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'previews.*.timer' => ['nullable', 'integer', 'min:5', 'max:900'],
            'previews.*.hint' => ['nullable', 'string', 'max:500'],
            'previews.*.tags' => ['nullable', 'array', 'max:10'],
            'previews.*.tags.*' => ['string', 'max:50'],
            'previews.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'question_bank_id.required' => 'Question Bank wajib dipilih.',
            'question_bank_id.exists' => 'Question Bank tidak ditemukan.',
            'previews.required' => 'Data soal wajib diisi.',
            'previews.min' => 'Minimal 1 soal untuk disimpan.',
        ];
    }
}
