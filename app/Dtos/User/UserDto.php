<?php
namespace App\Dtos\User;
use Illuminate\Http\Request;

class UserDto{
    private string $name;
    private string $last_name;
    private string $email;
    private string $password;

    public function __construct(Request $req){
        $req->validate([
            "name" => "required|string",
            "last_name" =>"required|string",
            "email" => "required|email|unique:users,email",
            "password" => "required|string|min:8"
        ],[
            "email.unique" => "El correo electrónico ya está registrado.",
            "email.required" => "El email es obligatorio",
            "password.required" => "La contraseña es obligatoria",
            "password.min" => "La contraseña debe tener minimo 8 digitos"
        ]);

        $this->name = $req->name;
        $this->last_name = $req->last_name;
        $this->email = $req->email;
        $this->password = $req->password;
    }

    public function toArray(){
        return [
            "name" => $this->name,
            "last_name" => $this->last_name,
            "email" => $this->email,
            "password" => $this->password
        ];
    }
}