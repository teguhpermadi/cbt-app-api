<?php

namespace App\Http\Requests\Api\V1\Exam;

use App\Enums\ExamTimerTypeEnum;
use App\Enums\ExamTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'ulid', 'exists:academic_years,id'],
            'subject_id' => ['required', 'ulid', 'exists:subjects,id'],
            'classroom_ids' => ['required', 'array', 'min:1'],
            'classroom_ids.*' => ['required', 'ulid', 'exists:classrooms,id'],
            'user_id' => ['required', 'ulid', 'exists:users,id'],
            'question_bank_id' => ['nullable', 'ulid', 'exists:question_banks,id'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ExamTypeEnum::class)],
            'duration' => ['required', 'integer', 'min:1'],
            'token' => ['nullable', 'string', 'max:50'],
            'is_token_visible' => ['boolean'],
            'is_published' => ['boolean'],
            'is_randomized_question' => ['boolean'],
            'is_randomized_answer' => ['boolean'],
            'is_show_result' => ['boolean'],
            'is_visible_hint' => ['boolean'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'timer_type' => ['required', new Enum(ExamTimerTypeEnum::class)],
            'passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after:start_time'],
        ];
    }
}
