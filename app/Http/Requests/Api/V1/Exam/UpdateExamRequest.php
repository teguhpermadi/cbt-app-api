<?php

namespace App\Http\Requests\Api\V1\Exam;

use App\Enums\ExamTimerTypeEnum;
use App\Enums\ExamTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['sometimes', 'ulid', 'exists:academic_years,id'],
            'subject_id' => ['sometimes', 'ulid', 'exists:subjects,id'],
            'classroom_ids' => ['sometimes', 'array', 'min:1'],
            'classroom_ids.*' => ['required', 'ulid', 'exists:classrooms,id'],
            'user_id' => ['sometimes', 'ulid', 'exists:users,id'],
            'question_bank_id' => ['sometimes', 'nullable', 'ulid', 'exists:question_banks,id'],
            'title' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', new Enum(ExamTypeEnum::class)],
            'duration' => ['sometimes', 'integer', 'min:1'],
            'token' => ['nullable', 'string', 'max:50'],
            'is_token_visible' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'is_randomized_question' => ['sometimes', 'boolean'],
            'is_randomized_answer' => ['sometimes', 'boolean'],
            'is_show_result' => ['sometimes', 'boolean'],
            'is_visible_hint' => ['sometimes', 'boolean'],
            'max_attempts' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'timer_type' => ['sometimes', new Enum(ExamTimerTypeEnum::class)],
            'passing_score' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'start_time' => ['sometimes', 'nullable', 'date'],
            'end_time' => ['sometimes', 'nullable', 'date', 'after:start_time'],
        ];
    }
}
