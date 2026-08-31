<?php

namespace App\utils;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class LoggerHelper {

    public static function error(string $message, array $context = []){
        Log::error($message, self::enrich($context));
    }

    public static function critical(string $message, array $context = []){
        Log::critical($message, self::enrich($context));
    }

    public static function exception(\Exception $e){
        Log::error("Excepción no controlada: " . $e->getMessage(), self::enrich([
            'exception' => get_class($e),
            'trace' => $e->getTraceAsString(),
        ]));
        abort(500, "Error interno del servidor");
    }

    private static function enrich(array $context): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? [];

        $context['file'] = $caller['file'] ?? 'unknown';
        $context['line'] = $caller['line'] ?? 0;
        $context['ip'] = Request::ip();
        $context['method'] = Request::method();
        $context['path'] = Request::path();

        $userId = Auth::id();
        if ($userId) {
            $context['user_id'] = $userId;
        }

        return $context;
    }
}
