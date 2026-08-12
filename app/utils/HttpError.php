<?php
namespace App\utils;

use Exception;

class HttpError {

    public static function InternalError(Exception $e){
        echo $e;
        abort(500,"Error interno del servidor");
    }

}