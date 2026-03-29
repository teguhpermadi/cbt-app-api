<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Learning;

use Illuminate\Foundation\Http\FormRequest;

final class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp3,wav,mp4,mov', 'max:102400'],
            'collection' => ['nullable', 'string', 'in:reading_files,videos,audios'],
        ];
    }
}
