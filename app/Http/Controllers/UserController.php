<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Services\UserService;
use App\utils\HttpError;
use App\utils\Security;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    private function __construct(
        private UserService $userService
    ){}

    public function getUser(string $id){
        try{
            return response()->json($this->userService->getById($id));
        }catch(ModelNotFoundException $e){
            return abort(404,"Usuario no encontrado")     ;   
        }catch(Exception $e){
            return abort(500,"Error interno del servidor");
        }
    }

    #[OA\Patch(
        path: "/api/user",
        tags: ["User Management"],
        summary: "Actualizar contraseña",
        description: "Cambia la contraseña del usuario autenticado.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["password", "newPassword"],
                properties: [
                    new OA\Property(property: "password", type: "string", format: "password", example: "oldpassword"),
                    new OA\Property(property: "newPassword", type: "string", format: "password", minLength: 8, example: "newsecretpassword"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Contraseña actualizada",
                content: new OA\JsonContent(type: "object")
            ),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function patchPassword(UpdatePasswordRequest $req){
        try{
            $user = Security::isOwner();
            return response()->json($this->userService->updatePass($user,$req->password,$req->newPassword));
        }catch(Exception $e){
            HttpError::InternalError($e); 
        }
    }

    #[OA\Put(
        path: "/api/user",
        tags: ["User Management"],
        summary: "Actualizar usuario",
        description: "Actualiza el nombre del usuario autenticado.",
        security: [["bearerAuth" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["name", "last_name"],
                properties: [
                    new OA\Property(property: "name", type: "string", example: "Juan"),
                    new OA\Property(property: "last_name", type: "string", example: "Pérez"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Usuario actualizado",
                content: new OA\JsonContent(type: "object")
            ),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function putUser(UpdateUserRequest $req){
        try{
            $user = Security::isOwner();
            return response()->json($this->userService->update($user,$req->validated()));
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }

    #[OA\Delete(
        path: "/api/user",
        tags: ["User Management"],
        summary: "Eliminar usuario",
        description: "Elimina permanentemente la cuenta del usuario autenticado.",
        security: [["bearerAuth" => []]],
        responses: [
            new OA\Response(response: 200, description: "Usuario eliminado"),
            new OA\Response(response: 401, description: "No autenticado"),
        ]
    )]
    public function deleteUser(){
        try{
            $user = Security::isOwner();
            return response()->json($this->userService->delete($user));
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }
}
