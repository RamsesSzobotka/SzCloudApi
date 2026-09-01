<?php

namespace App\Http\Requests\Storage;

use Illuminate\Foundation\Http\FormRequest;

class InitUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "file_name" => ["required", "string", "max:255"],
            "mime_type" => ["required", "string", "max:127"],
            "total_size" => ["required", "integer", "min:1"],
            "folder_id" => ["nullable", "string"],
        ];
    }
}
