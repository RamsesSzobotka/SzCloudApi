<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
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
        File::onlyTrashed()
            ->where("deleted_at", "<=", now()->subDays(30))
            ->forceDelete();

        Folder::onlyTrashed()
            ->where("deleted_at", "<=", now()->subDays(30))
            ->forceDelete();
    }
}
