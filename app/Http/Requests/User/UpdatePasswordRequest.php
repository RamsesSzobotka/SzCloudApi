<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdatePasswordRequest",
    required: ["password", "newPassword"],
    properties: [
        new OA\Property(property: "password", type: "string", format: "password", example: "oldpassword"),
        new OA\Property(property: "newPassword", type: "string", format: "password", minLength: 8, example: "newsecretpassword"),
    ]
)]
class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "password" => "required|string",
            "newPassword" => "required|string|min:8",
        ];
    }

    public function messages(): array
    {
        return [
            "password.required" => "La contraseña actual es obligatoria.",
            "newPassword.required" => "La nueva contraseña es obligatoria.",
            "newPassword.min" => "La nueva contraseña debe tener mínimo 8 caracteres.",
        ];
    }
}
