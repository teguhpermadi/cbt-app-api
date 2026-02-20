<?php

namespace App\Http\Requests\Api\V1\ReadingMaterial;

use Illuminate\Foundation\Http\FormRequest;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,mp3,wav,mp4,mov,avi,pdf', 'max:10240'], // Added pdf
            'collection' => ['nullable', 'string', 'in:reading_materials,question_content,option_media'],
        ];
    }
}
