<?php

namespace App\utils\ExceptionCustom;

class CarpetaEliminadaException extends StorageException{

    public function __construct(){
        parent::__construct("No se puede realizar esta operación con una carpeta que fue eliminada");
    }
}
