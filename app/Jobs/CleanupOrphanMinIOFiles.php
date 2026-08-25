<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use App\Models\File;
use App\Models\FileVersion;
use App\utils\MinIOHelper;
use App\utils\LoggerHelper;

class CleanupOrphanMinIOFiles implements ShouldQueue
{
    use Queueable;

    private int $deletedCount = 0;
    private int $scannedCount = 0;

    public function __construct()
    {
    }

    public function handle(): void
    {
        $disk = Storage::disk('minio');
        $adapter = $disk->getDriver()->getAdapter();

        if (!method_exists($adapter, 'getClient')) {
            LoggerHelper::error("CleanupOrphanMinIOFiles: adapter no soporta getClient()");
            return;
        }

        $client = $adapter->getClient();
        $bucket = config('filesystems.disks.minio.bucket');

        $this->processDirectory($client, $bucket, '');

        LoggerHelper::info("CleanupOrphanMinIOFiles completado", [
            "scanned" => $this->scannedCount,
            "deleted" => $this->deletedCount,
        ]);
    }

    private function processDirectory($client, string $bucket, string $prefix): void
    {
        $continuationToken = null;

        do {
            $params = [
                'Bucket' => $bucket,
                'Prefix' => $prefix,
                'Delimiter' => '/',
            ];

            if ($continuationToken) {
                $params['ContinuationToken'] = $continuationToken;
            }

            $result = $client->listObjectsV2($params);

            foreach ($result['Contents'] ?? [] as $object) {
                $key = $object['Key'];
                $this->scannedCount++;

                if ($this->isOrphan($key)) {
                    $client->deleteObject([
                        'Bucket' => $bucket,
                        'Key' => $key,
                    ]);
                    $this->deletedCount++;
                }
            }

            foreach ($result['CommonPrefixes'] ?? [] as $commonPrefix) {
                $this->processDirectory($client, $bucket, $commonPrefix['Prefix']);
            }

            $continuationToken = $result['NextContinuationToken'] ?? null;
        } while ($continuationToken);
    }

    private function isOrphan(string $storagePath): bool
    {
        $existsInFiles = File::where("storage_path", $storagePath)->exists();
        if ($existsInFiles) {
            return false;
        }

        $existsInVersions = FileVersion::where("storage_path", $storagePath)->exists();
        if ($existsInVersions) {
            return false;
        }

        return true;
    }
}
