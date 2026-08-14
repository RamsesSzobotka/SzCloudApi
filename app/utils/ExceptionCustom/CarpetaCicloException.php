<?php

namespace App\utils\ExceptionCustom;

class CarpetaCicloException extends StorageException{

    public function __construct(){
        parent::__construct("No se puede mover una carpeta dentro de una de sus subcarpetas");
    }
}
