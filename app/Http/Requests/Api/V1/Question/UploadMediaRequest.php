<?php

namespace App\Http\Requests\Api\V1\Question;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
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
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,mp3,wav,mp4,mov,avi', 'max:10240'], // 10MB max
            'collection' => ['nullable', 'string', 'in:question_content'],
        ];
    }
}
