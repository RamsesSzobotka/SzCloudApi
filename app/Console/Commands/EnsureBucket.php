<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;

class EnsureBucket extends Command
{
    protected $signature = 'storage:ensure-bucket';
    protected $description = 'Create the MinIO bucket if it does not exist';

    public function handle(): int
    {
        $config = config('filesystems.disks.minio');

        $s3 = new S3Client([
            'endpoint'       => $config['endpoint'],
            'region'         => $config['region'],
            'version'        => 'latest',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key'    => $config['key'],
                'secret' => $config['secret'],
            ],
        ]);

        $bucket = $config['bucket'];

        if ($s3->doesBucketExist($bucket)) {
            $this->info("Bucket [{$bucket}] already exists.");
            return self::SUCCESS;
        }

        $s3->createBucket(['Bucket' => $bucket]);
        $this->info("Bucket [{$bucket}] created.");
        return self::SUCCESS;
    }
}
