<?php

namespace App\utils;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class LoggerHelper {

    public static function info(string $message, array $context = []){
        Log::info($message, self::enrich($context));
    }

    public static function warning(string $message, array $context = []){
        Log::warning($message, self::enrich($context));
    }

    public static function error(string $message, array $context = []){
        Log::error($message, self::enrich($context));
    }

    public static function debug(string $message, array $context = []){
        Log::debug($message, self::enrich($context));
    }

    public static function critical(string $message, array $context = []){
        Log::critical($message, self::enrich($context));
    }

    private static function enrich(array $context): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $trace[1] ?? [];

        $context['file'] = $caller['file'] ?? 'unknown';
        $context['line'] = $caller['line'] ?? 0;
        $context['ip'] = Request::ip();

        return $context;
    }
}
