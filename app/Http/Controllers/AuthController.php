<?php

namespace App\Http\Controllers;

use App\Dtos\User\UserDto;
use App\Services\UserService;
use App\utils\ExceptionCustom\DuplicateException;
use App\utils\HttpError;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private UserService $userService,
    )
    {}

    public function login(Request $req){
        try{
            $credentials = $req->validate([
                "email" => "required|email",
                "password" => "required|string"
            ],
            [
                "email.required" => "El correo electrónico es obligatorio.",
                "password.required" => "La contraseña es obligatoria."
            ]);

            if(!$token = auth("api")->attempt($credentials)){
                return abort(401,"credenciales incorrectas");
            }

            return response()->json([
                "access_token" => $token,
                "token_type" => "bearer",
            ]);
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }

    public function logout(){
        try{
            auth("api")->logout();

            return response()->json();
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }

    public function register(Request $req){
        try {
            $newUser = $this->userService->add(new UserDto($req));

            return response()->json([
                'message' => 'Usuario registrado correctamente.',
                'user' => $newUser,
            ], 201);

        } catch(DuplicateException $e){
            abort(409,"El usuario ya se encuentra registrado");
        } catch (Exception $e) {
            HttpError::InternalError($e);
        }
    }


    public function getMe(){
        try{
            return response()->json(auth('api')->user());
        }catch(ModelNotFoundException $e){
            return abort(404,"Usuario no encontrado")     ;   
        }catch(Exception $e){
            return abort(500,"Error interno del servidor");
        }
    }
}
