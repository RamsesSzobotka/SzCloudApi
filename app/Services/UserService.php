<?php

namespace App\Services;

use App\Models\User;
use App\utils\ExceptionCustom\DuplicateException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;

class UserService{

    public function getById(string $id){
        return User::FindOrFail($id);
    }

    public  function add(array $data){
        try{
            $data['password'] = Hash::make($data['password']);
            return User::create($data);
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

    public function update(User $user, array $data){
        return $user->updateOrFail($data);
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
