<?php

namespace App\utils;

use Illuminate\Support\Facades\Cache;

class RedisUploadHelper {

    private const PREFIX = 'upload:';
    private const TTL_HOURS = 24;

    public static function init(string $sessionId, int $totalParts, int $totalSize): void {
        $prefix = self::PREFIX . $sessionId;
        $ttl = self::TTL_HOURS * 3600;
        $redis = Cache::store('redis');

        $redis->put("{$prefix}:meta", [
            'total_parts' => $totalParts,
            'total_size' => $totalSize,
            'uploaded_size' => 0,
        ], $ttl);

        $redis->put("{$prefix}:parts", [], $ttl);
        $redis->put("{$prefix}:errors", [], $ttl);
    }

    public static function addPart(string $sessionId, int $partNumber, string $etag, int $chunkSize): void {
        $prefix = self::PREFIX . $sessionId;
        $redis = Cache::store('redis');
        $ttl = self::TTL_HOURS * 3600;

        $parts = $redis->get("{$prefix}:parts") ?? [];
        $parts[] = ['part_number' => $partNumber, 'etag' => $etag];
        $redis->put("{$prefix}:parts", $parts, $ttl);

        $meta = $redis->get("{$prefix}:meta") ?? [];
        $meta['uploaded_size'] = ($meta['uploaded_size'] ?? 0) + $chunkSize;
        $redis->put("{$prefix}:meta", $meta, $ttl);
    }

    public static function getParts(string $sessionId): array {
        return Cache::store('redis')->get(self::PREFIX . $sessionId . ':parts') ?? [];
    }

    public static function getMeta(string $sessionId): array {
        return Cache::store('redis')->get(self::PREFIX . $sessionId . ':meta') ?? [];
    }

    public static function getUploadedSize(string $sessionId): int {
        return self::getMeta($sessionId)['uploaded_size'] ?? 0;
    }

    public static function isPartUploaded(string $sessionId, int $partNumber): ?array {
        $parts = self::getParts($sessionId);
        foreach ($parts as $part) {
            if ($part['part_number'] === $partNumber) {
                return $part;
            }
        }
        return null;
    }

    public static function addError(string $sessionId, string $error, ?int $partNumber = null): void {
        $prefix = self::PREFIX . $sessionId;
        $redis = Cache::store('redis');
        $ttl = self::TTL_HOURS * 3600;

        $errors = $redis->get("{$prefix}:errors") ?? [];
        $errors[] = [
            'error' => $error,
            'part_number' => $partNumber,
            'timestamp' => now()->toIso8601String(),
        ];
        $redis->put("{$prefix}:errors", $errors, $ttl);
    }

    public static function hasErrors(string $sessionId): bool {
        $errors = self::getErrors($sessionId);
        return count($errors) > 0;
    }

    public static function getErrors(string $sessionId): array {
        return Cache::store('redis')->get(self::PREFIX . $sessionId . ':errors') ?? [];
    }

    public static function cleanup(string $sessionId): void {
        $prefix = self::PREFIX . $sessionId;
        $redis = Cache::store('redis');

        $redis->forget("{$prefix}:meta");
        $redis->forget("{$prefix}:parts");
        $redis->forget("{$prefix}:errors");
    }
}