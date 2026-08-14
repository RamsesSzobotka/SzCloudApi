<?php

namespace App\utils\ExceptionCustom;

class CarpetaMovimientoPropioException extends StorageException{

    public function __construct(){
        parent::__construct("No se puede mover una carpeta dentro de sí misma");
    }
}
