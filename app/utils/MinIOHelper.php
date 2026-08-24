<?php

namespace App\utils;

use Illuminate\Support\Facades\Storage;

class MinIOHelper {

    public static function put(string $path, $content): bool {
        return Storage::disk('minio')->put($path, $content);
    }

    public static function get(string $path): string {
        return Storage::disk('minio')->get($path);
    }

    public static function delete(string $path): bool {
        return Storage::disk('minio')->delete($path);
    }

    public static function deleteBatch(array $paths): void {
        foreach ($paths as $path) {
            Storage::disk('minio')->delete($path);
        }
    }
}
