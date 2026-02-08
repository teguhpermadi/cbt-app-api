<?php

namespace App\Http\Requests\Api\V1\AcademicYear;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAcademicYearRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'year' => ['sometimes', 'string', 'max:20'],
            'semester' => ['sometimes', 'string', 'max:20'],
            'user_id' => ['sometimes', 'ulid', 'exists:users,id'],
        ];
    }
}
