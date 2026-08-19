<?php

namespace App\Services;

use App\Models\StorageUsage;
use App\Models\User;

class StorageUsageService
{
    public function addNewStorage(string $userId)
    {
        return StorageUsage::create([
            "user_id" => $userId,
            "used_bytes" => 0,
            "file_count" => 0,
        ]);
    }

    public function storageVerify(User $user, int $fileSize): bool
    {
        $plan = $user->plan();
        $usage = $user->storageUsage();

        if (!$plan || !$usage) {
            return false;
        }

        return ($usage->used_bytes + $fileSize) <= $plan->storage_limit;
    }

    public function getStorageInfo(User $user): array
    {
        return [
            "storage_usage" => $user->storageUsage(),
            "plan" => $user->plan(),
        ];
    }

    public function getStorage(User $user)
    {
        return $user->storageUsage();
    }

    public function addFile(StorageUsage $storageUsage, int $fileSize)
    {
        return $storageUsage->update([
            "used_bytes" => $storageUsage->used_bytes + $fileSize,
            "file_count" => $storageUsage->file_count + 1,
        ]);
    }

    public function deleteFile(StorageUsage $storageUsage, int $fileSize)
    {
        return $storageUsage->update([
            "used_bytes" => max(0, $storageUsage->used_bytes - $fileSize),
            "file_count" => max(0, $storageUsage->file_count - 1),
        ]);
    }
}
