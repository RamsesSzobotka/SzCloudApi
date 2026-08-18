<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use App\Models\File;
use App\Models\Folder;
class DeleteExpiredTrash implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $expiredFiles = File::onlyTrashed()
            ->where("deleted_at", "<=", now()->subDays(30))
            ->get();

        foreach ($expiredFiles as $file) {
            Storage::disk('minio')->delete($file->storage_path);
            $file->forceDelete();
        }

        Folder::onlyTrashed()
            ->where("deleted_at", "<=", now()->subDays(30))
            ->forceDelete();
    }
}
