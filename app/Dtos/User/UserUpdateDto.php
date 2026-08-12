<?php
namespace App\Dtos\User;
use Illuminate\Http\Request;

class UserUpdateDto{
    private string $name;
    private string $last_name;

    public function __construct(Request $req){
        $req->validate([
            "name" => "required|string",
            "last_name" =>"required|string",
            "email" => "required|email|unique:users,email"
        ]);

        $this->name = $req->name;
        $this->last_name = $req->last_name;
    }

    public function toArray(){
        return array_filter([
            "name" => $this->name,
            "last_name" => $this->last_name
            ], fn($v) => $v !== null);
    }
}