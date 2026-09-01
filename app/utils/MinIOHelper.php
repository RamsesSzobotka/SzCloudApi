<?php

namespace App\utils;

use Illuminate\Support\Facades\Storage;
use App\Models\File;
use Aws\S3\S3Client;

class MinIOHelper {

    public static function putStream(string $path, $resource): bool {
        return Storage::disk('minio')->writeStream($path, $resource);
    }

    public static function getStream(string $path) {
        return Storage::disk('minio')->readStream($path);
    }

    /**
     * Stream a resource to MinIO while computing SHA-256 in one pass.
     * Returns [success, hash].
     */
    public static function putStreamWithHash(string $path, $resource): array {
        $context = hash_init('sha256');
        $tmp = fopen('php://temp', 'r+');

        stream_copy_to_stream($resource, $tmp);
        hash_update_stream($context, $tmp);
        rewind($tmp);

        Storage::disk('minio')->writeStream($path, $tmp);
        fclose($tmp);

        return [true, hash_final($context)];
    }

    public static function delete(string $path): bool {
        return Storage::disk('minio')->delete($path);
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

    private static function getClient(): S3Client {
        return new S3Client([
            'credentials' => [
                'key'    => config('filesystems.disks.minio.key'),
                'secret' => config('filesystems.disks.minio.secret'),
            ],
            'region'  => config('filesystems.disks.minio.region'),
            'endpoint' => config('filesystems.disks.minio.endpoint'),
            'use_path_style_endpoint' => true,
            'version' => 'latest',
        ]);
    }

    public static function createMultipartUpload(string $path): array {
        $client = self::getClient();
        $result = $client->execute($client->getCommand('CreateMultipartUpload', [
            'Bucket' => config('filesystems.disks.minio.bucket'),
            'Key'    => $path,
        ]));

        return [
            'upload_id' => $result['UploadId'],
            'key'       => $path,
            'bucket'    => config('filesystems.disks.minio.bucket'),
        ];
    }

    public static function uploadPart(string $path, string $uploadId, int $partNumber, $body, int $size): array {
        $client = self::getClient();
        $result = $client->execute($client->getCommand('UploadPart', [
            'Bucket'        => config('filesystems.disks.minio.bucket'),
            'Key'           => $path,
            'UploadId'      => $uploadId,
            'PartNumber'    => $partNumber,
            'Body'          => $body,
            'ContentLength' => $size,
        ]));

        return ['part_number' => $partNumber, 'etag' => $result['ETag']];
    }

    public static function completeMultipartUpload(string $path, string $uploadId, array $parts): bool {
        $client = self::getClient();
        usort($parts, fn($a, $b) => $a['part_number'] <=> $b['part_number']);
        $parts = array_map(fn($p) => ['PartNumber' => $p['part_number'], 'ETag' => $p['etag']], $parts);

        $client->execute($client->getCommand('CompleteMultipartUpload', [
            'Bucket'   => config('filesystems.disks.minio.bucket'),
            'Key'      => $path,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]));

        return true;
    }

    public static function abortMultipartUpload(string $path, string $uploadId): bool {
        $client = self::getClient();
        $client->execute($client->getCommand('AbortMultipartUpload', [
            'Bucket'   => config('filesystems.disks.minio.bucket'),
            'Key'      => $path,
            'UploadId' => $uploadId,
        ]));

        return true;
    }

}
