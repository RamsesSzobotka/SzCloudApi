<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\File;
use App\Models\Folder;
use App\utils\MinIOHelper;

class DeleteExpiredTrash implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(): void
    {
        $expiredFiles = File::onlyTrashed()
            ->where("deleted_at", "<=", now()->subDays(30))
            ->get();

        foreach ($expiredFiles as $file) {
            foreach ($file->versions as $version) {
                MinIOHelper::delete($version->storage_path);
            }
            $file->forceDelete();
        }

        Folder::onlyTrashed()
            ->where("deleted_at", "<=", now()->subDays(30))
            ->forceDelete();
    }
}
