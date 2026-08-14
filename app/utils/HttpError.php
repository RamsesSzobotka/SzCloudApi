<?php
namespace App\utils;

use Exception;

class HttpError {

    public static function InternalError(Exception $e){
        LoggerHelper::error("Excepción no controlada: " . $e->getMessage(), [
            "exception" => get_class($e),
            "trace" => $e->getTraceAsString()
        ]);
        abort(500, "Error interno del servidor");
    }

}
