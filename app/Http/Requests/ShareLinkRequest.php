<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShareLinkRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "expires_at" => ["nullable","date_format:Y-m-d\TH:i:s","after:now"],
            "max_downloads" => ["nullable","integer","min:1"],
            "password" => ["nullable","string"],
        ];
    }

    public function messages(): array
    {
        return [
            "expires_at.date_format" => "El formato de fecha debe ser YYYY-MM-DDTHH:MM:SS",
            "expires_at.after" => "La fecha de expiración no puede ser en el pasado",
            "max_downloads.integer" => "El máximo de descargas debe ser un número entero",
            "max_downloads.min" => "El máximo de descargas debe ser al menos 1",
            "password.string" => "La contraseña debe ser un texto válido",
        ];
    }
}
