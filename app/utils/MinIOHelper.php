<?php

namespace App\utils;

use Illuminate\Support\Facades\Storage;
use App\Models\File;
use Aws\S3\S3Client;

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

    public static function urlDownloadFile(File $file, int $minutes = 30){
        $publicClient = new S3Client([
            'credentials' => [
                'key'    => config('filesystems.disks.minio.key'),
                'secret' => config('filesystems.disks.minio.secret'),
            ],
            'region'  => config('filesystems.disks.minio.region'),
            'endpoint' => config('filesystems.disks.minio.public_endpoint', config('filesystems.disks.minio.endpoint')),
            'use_path_style_endpoint' => true,
            'version' => 'latest',
        ]);

        $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $file->original_name);

        $command = $publicClient->getCommand('GetObject', [
            'Bucket' => config('filesystems.disks.minio.bucket'),
            'Key'    => $file->storage_path,
            'ResponseContentDisposition' =>
                'attachment; filename="' . $safeName . '"',
        ]);

        return (string) $publicClient->createPresignedRequest(
            $command, now()->addMinutes($minutes)
        )->getUri();
    }

}
