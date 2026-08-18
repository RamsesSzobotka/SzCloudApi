<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "LoginRequest",
    required: ["email", "password"],
    properties: [
        new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
        new OA\Property(property: "password", type: "string", format: "password", example: "secretpassword"),
    ]
)]
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "email" => "required|email",
            "password" => "required|string",
        ];
    }

    public function messages(): array
    {
        return [
            "email.required" => "El correo electrónico es obligatorio.",
            "password.required" => "La contraseña es obligatoria.",
        ];
    }
}
