<?php

namespace App\utils;

class NameSanitizer
{
    private const INVALID_CHARS = ['/', '\\', ':', '"', "'", '<', '>', '|'];

    public static function isValid(string $name): bool
    {
        return !strpbrk($name, implode('', self::INVALID_CHARS));
    }

    public static function analyze(string $name): array
    {
        $valid = self::isValid($name);
        return [
            'original_name' => $name,
            'valid' => $valid,
        ];
    }
}
