<?php

namespace App\utils;

class NameSanitizer
{
    private const INVALID_CHARS = ['/', '\\', ':', '"', "'", '<', '>', '|'];

    public static function isValid(string $name): bool
    {
        return !strpbrk($name, implode('', self::INVALID_CHARS));
    }

}
