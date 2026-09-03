<?php

namespace App\Http\Requests\Storage;

use Illuminate\Foundation\Http\FormRequest;

class MoveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "destination_folder_id" => ["nullable", "string"],
            "overwrite" => ["nullable", "boolean"],
        ];
    }
}
