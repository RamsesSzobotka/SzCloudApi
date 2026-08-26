<?php

namespace App\utils\ExceptionCustom;

use Exception;

class PermisoDenegadoException extends Exception
{
    public function __construct(string $mensaje = 'No tienes permiso para realizar esta acción')
    {
        parent::__construct($mensaje, 403);
    }
}
