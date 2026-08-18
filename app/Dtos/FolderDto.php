<?php
namespace App\Dtos;

class FolderDto{
    private string $user_id;
    private ?string $parent_id;
    private string $name;

    public function __construct(string $user_id,string $name,?string $parent_id = null){

        $this->user_id = $user_id;
        $this->parent_id = $parent_id;
        $this->name = $name;
    }

    public function toArray() : array {
        return [
            "user_id" => $this->user_id,
            "parent_id" => $this->parent_id,
            "name" => $this->name
        ];
    }
}