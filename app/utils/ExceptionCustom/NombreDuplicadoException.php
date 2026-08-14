<?php

namespace App\utils\ExceptionCustom;

class NombreDuplicadoException extends StorageException{

    public function __construct(string $tipo = "recurso"){
        parent::__construct("Ya existe un {$tipo} con ese nombre en esta ubicación");
    }
}
