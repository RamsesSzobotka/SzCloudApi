<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "RegisterRequest",
    required: ["name", "email", "password"],
    properties: [
        new OA\Property(property: "name", type: "string", example: "Juan Pérez"),
        new OA\Property(property: "email", type: "string", format: "email", example: "juan@example.com"),
        new OA\Property(property: "password", type: "string", format: "password", minLength: 8, example: "secretpassword"),
    ]
)]
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "name" => "required|string",
            "email" => "required|email|unique:users,email",
            "password" => "required|string|min:8",
        ];
    }

    public function messages(): array
    {
        return [
            "name" => "El nombre es obligatorio",
            "email.unique" => "El correo electrónico ya está registrado.",
            "email.required" => "El email es obligatorio",
            "password.required" => "La contraseña es obligatoria",
            "password.min" => "La contraseña debe tener minimo 8 digitos",
        ];
    }
}
