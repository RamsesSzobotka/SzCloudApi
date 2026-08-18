<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateUserRequest",
    required: ["name", "last_name"],
    properties: [
        new OA\Property(property: "name", type: "string", example: "Juan"),
        new OA\Property(property: "last_name", type: "string", example: "Pérez"),
    ]
)]
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "name" => "required|string",
            "last_name" => "required|string",
        ];
    }
}
