<?php

namespace App\Http\Controllers;

use App\Dtos\User\UserUpdateDto;
use App\Services\UserService;
use App\utils\HttpError;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

    public function patchPassword(Request $req){
        try{
            if(!$user = auth("api")->user()){
                abort(401);
            }
            
            return response()->json($this->userService->updatePass($user,$req->password,$req->newPassword));
        }catch(Exception $e){
            HttpError::InternalError($e); 
        }
    }

    public function putUser(Request $req){
        try{
            if(!$user = auth("api")->user()){
                abort(401);
            }

            return response()->json($this->userService->update($user,new UserUpdateDto($req)));
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }

    public function deleteUser(){
        try{
            if(!$user = auth("api")->user()){
                abort(401);
            }

            return response()->json($this->userService->delete($user));
        }catch(Exception $e){
            HttpError::InternalError($e);
        }
    }
}
