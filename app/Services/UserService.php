<?php
namespace App\Services;
use App\Models\User;
use App\utils\ExceptionCustom\DuplicateException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use App\Dtos\User\UserDto;
use App\Dtos\User\UserUpdateDto;

class UserService{

    public function getById(string $id){
        return User::FindOrFail($id);
    }

    public  function add(UserDto $user){
        try{
            return User::create($user->toArray());
        }catch (QueryException $e){

            if($e->getCode() === '23505') {
                throw new DuplicateException();
            }
            throw $e; 
        }
    }

    public function delete(User $user){
        return $user->deleteOrFail();
    }

    public function update(User $user, UserUpdateDto $newData){
        return $user->updateOrFail($newData->toArray());
    }

    public function updatePass(User $user,string $password, string $newPassword){
        if(!Hash::check($password,$user->password)){
            return false;
        }
        return $user->updateOrFail([
            "password" => Hash::make($newPassword)
        ]);
    }
}