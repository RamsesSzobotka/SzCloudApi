<?php

namespace App\Services;

use App\Models\Expansion;
use App\Models\User;
use App\Models\UserExpansion;
use Illuminate\Support\Facades\DB;

class StorageUsageService
{
    public function storageVerify(User $user, int $fileSize): bool
    {
        return ($user->storage_used + $fileSize) <= $user->storage_limit;
    }

    public function getStorageInfo(User $user): array
    {
        return [
            "storage_limit" => $user->storage_limit,
            "storage_used" => $user->storage_used,
            "file_count" => $user->file_count,
            "expansions" => $user->expansions,
        ];
    }

    public function addFile(User $user, int $fileSize)
    {
        return $user->update([
            "storage_used" => $user->storage_used + $fileSize,
            "file_count" => $user->file_count + 1,
        ]);
    }

    public function deleteFile(User $user, int $fileSize)
    {
        return $user->update([
            "storage_used" => max(0, $user->storage_used - $fileSize),
            "file_count" => max(0, $user->file_count - 1),
        ]);
    }

    public function applyExpansion(User $user, Expansion $expansion): bool
    {
        return DB::transaction(function () use ($user, $expansion) {
            $userExpansion = UserExpansion::create([
                "user_id" => $user->id,
                "expansion_id" => $expansion->id,
            ]);

            if (!$userExpansion) {
                return false;
            }

            return $user->update([
                "storage_limit" => $user->storage_limit + $expansion->storage_bytes,
            ]);
        });
    }
}
