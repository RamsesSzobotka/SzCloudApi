<?php
namespace App\Services;

use App\Models\Folder;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\FileActivity;
use App\Models\User;
use App\utils\ExceptionCustom\NombreDuplicadoException;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use \App\utils\ExceptionCustom\StorageException;
use Aws\S3\S3Client;

class FileService {

    public function __construct(
        private StorageUsageService $storageUsageService,
        private FolderService $folderService,
    ){}

    public function addFile(User $user, UploadedFile $file, ?string $folderId = null){
        return DB::transaction(function () use ($user, $file, $folderId) {

            $fileSize = $file->getSize();

            if (!$this->storageUsageService->storageVerify($user, $fileSize)) {
                throw new StorageException("No tienes suficiente espacio de almacenamiento");
            }

            if ($folderId !== null) {
                Folder::where("id", $folderId)
                    ->where("user_id", $user->id)
                    ->firstOrFail();
            }

            $conflictQuery = File::where("user_id", $user->id)
                ->where("original_name", $file->getClientOriginalName())
                ->whereNull("deleted_at");

            if ($folderId !== null) {
                $conflictQuery->where("folder_id", $folderId);
            } else {
                $conflictQuery->whereNull("folder_id");
            }

            $conflict = $conflictQuery->exists();

            if ($conflict){
                throw new NombreDuplicadoException("archivo");
            }

            $extension = $file->getClientOriginalExtension();
            $storageName = Str::uuid() . '.' . $extension;
            $storagePath = "users/{$user->id}/files/{$storageName}";
            $hash = hash_file('sha256', $file->getRealPath());

            Storage::disk('minio')->put($storagePath, file_get_contents($file->getRealPath()));

            $fileRecord = File::create([
                "user_id" => $user->id,
                "folder_id" => $folderId,
                "original_name" => $file->getClientOriginalName(),
                "storage_name" => $storageName,
                "storage_path" => $storagePath,
                "mime_type" => $file->getMimeType(),
                "extension" => $extension,
                "size" => $fileSize,
                "hash" => $hash,
            ]);

            $this->storageUsageService->addFile($user, $fileSize);
            $this->addFileVersion($fileRecord);
            $this->addFileActivity($fileRecord, 'create', null, $fileRecord->only(['original_name', 'folder_id', 'storage_name']));

            return $fileRecord;
        });
    }

    public function getFile(string $userId, string $fileId){
        return File::where("user_id", $userId)->where("id",$fileId)->firstOrFail();
    }

    public function getTrashedFile(string $userId, string $fileId){
        return File::withTrashed()->where("user_id", $userId)->where("id",$fileId)->firstOrFail();
    }

    public function moveFileToTrash(File $file){
        return $file->delete();
    }

    public function restoreFile(File $file){
        return DB::transaction(function () use ($file) {
            $folder = Folder::withTrashed()->where("id", $file->folder_id)->firstOrFail();
            $this->folderService->restoreParentFolders($folder);

            return $file->restore();
        });
    }

    public function updateFile(File $file, array $newFile, bool $logActivity = true){
        $oldHash = $file->hash;
        $oldValues = $logActivity ? $file->only(array_keys($newFile)) : null;

        $result = $file->update($newFile);

        if ($result && $file->hash !== $oldHash) {
            $this->addFileVersion($file);
            $this->deleteOldVersion($file);
        }

        if ($result && $logActivity) {
            $this->addFileActivity($file, 'update', $oldValues, $newFile);
        }

        return $result;
    }

    public function deletePermanentFile(File $file){
        Storage::disk('minio')->delete($file->storage_path);

        $user = User::findOrFail($file->user_id);
        $this->storageUsageService->deleteFile($user, $file->size);

        return $file->forceDelete();
    }

    public static function findAvailableName(string $userId, ?string $folderId, string $name, ?string $extension, ?string $excludeId = null): string {
        $baseName = pathinfo($name, PATHINFO_FILENAME);
        $ext = $extension ? "." . $extension : "";
        $candidate = $name;
        $number = 1;

        $query = File::where("user_id", $userId)
            ->where("folder_id", $folderId)
            ->where("original_name", $candidate);

        if ($excludeId) {
            $query->where("id", "!=", $excludeId);
        }

        while ($query->exists()) {
            $candidate = $baseName . " (" . $number . ")" . $ext;
            $number++;

            $query = File::where("user_id", $userId)
                ->where("folder_id", $folderId)
                ->where("original_name", $candidate);

            if ($excludeId) {
                $query->where("id", "!=", $excludeId);
            }
        }

        return $candidate;
    }

    public function checkFileName(string $userId, ?string $folderId, string $name): array {
        $exists = File::where("user_id", $userId)
            ->where("folder_id", $folderId)
            ->where("original_name", $name)
            ->exists();

        if (!$exists) {
            return ["exists" => false, "suggested_name" => null];
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suggested = self::findAvailableName($userId, $folderId, $name, $extension ?: null);

        return ["exists" => true, "suggested_name" => $suggested];
    }

    public function moveFile(File $file, ?string $folderId = null){
        if ($folderId !== null){
            $destination = Folder::where("id", $folderId)
                ->where("user_id", $file->user_id)
                ->firstOrFail();

            if ($destination->trashed()){
                throw new CarpetaEliminadaException();
            }
        }

        $conflict = File::where("user_id", $file->user_id)
            ->where("folder_id", $folderId)
            ->where("original_name", $file->original_name)
            ->where("id", "!=", $file->id)
            ->exists();

        $updates = ["folder_id" => $folderId];

        if ($conflict) {
            $updates["original_name"] = self::findAvailableName(
                $file->user_id, $folderId, $file->original_name, $file->extension, $file->id
            );
        }

        return $this->updateFile($file, $updates);
    }

    public function renameFile(File $file, string $newName){
        $available = self::findAvailableName(
            $file->user_id, $file->folder_id, $newName, $file->extension, $file->id
        );

        return $this->updateFile($file, ["original_name" => $available]);
    }

    public function urlDownloadFile(File $file){
        $publicClient = new S3Client([
            'credentials' => [
                'key'    => config('filesystems.disks.minio.key'),
                'secret' => config('filesystems.disks.minio.secret'),
            ],
            'region'  => config('filesystems.disks.minio.region'),
            'endpoint' => env('MINIO_PUBLIC_ENDPOINT', 'http://localhost:9000'),
            'use_path_style_endpoint' => true,
            'version' => 'latest',
        ]);

        $command = $publicClient->getCommand('GetObject', [
            
        'Bucket' => config('filesystems.disks.minio.bucket'),
            'Key'    => $file->storage_path,
            'ResponseContentDisposition' =>
                'attachment; filename="' . $file->original_name . '"',
        ]);

        return (string) $publicClient->createPresignedRequest(
            $command, now()->addMinutes(30)
        )->getUri();
    }
    
    public function addFileVersion(File $file){
        $lastVersion = $file->versions()->max('version') ?? 1;

        return FileVersion::create([
            "file_id" => $file->id,
            "version" => $lastVersion + 1,
            "storage_name" => $file->storage_name,
            "storage_path" => $file->storage_path,
            "mime_type" => $file->mime_type,
            "size" => $file->size,
            "hash" => $file->hash,
        ]);
    }

    public function addFileActivity(File $file, string $action, ?array $oldValues, ?array $newValues){
        $activity = FileActivity::create([
            "file_id" => $file->id,
            "action" => $action,
            "old_values" => $oldValues,
            "new_values" => $newValues,
        ]);

        $this->deleteOldActivity($file);

        return $activity;
    }

    public function deleteOldActivity(File $file){
        $count = $file->activities()->where('is_undone', false)->count();

        if ($count <= 3) {
            return false;
        }

        return $file->activities()
            ->where('is_undone', false)
            ->orderBy('created_at', 'asc')
            ->first()
            ?->delete();
    }

    public function getActivityLog(File $file){
        return $file->activities()->orderBy('created_at', 'desc')->get();
    }

    public function restoreBackActivity(File $file){
        $activity = $file->activities()
            ->where('is_undone', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$activity || !$activity->old_values) {
            return null;
        }

        $file->update($activity->old_values);
        $activity->update(['is_undone' => true]);

        return $file;
    }

    public function restoreFrontActivity(File $file){
        $activity = $file->activities()
            ->where('is_undone', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$activity || !$activity->new_values) {
            return null;
        }

        $file->update($activity->new_values);
        $activity->update(['is_undone' => false]);

        return $file;
    }

    public function getVersionsInfo(File $file){
        $versions = $file->versions()->orderBy('version', 'desc')->get();
        return [
            "versions" => $versions,
            "count" => $versions->count()
        ];
    }

    public function deleteOldVersion(File $file){
        $versions = $file->versions();

        if ($versions->count() <= 3) {
            return false;
        }

        $oldestVersion = $versions->orderBy('version', 'asc')->first();

        return $oldestVersion?->delete();
    }
    
    public function restoreBackVersion(File $file){
        $currentV = $file->versions()->max('version') ?? 1;
        $targetVersion = $file->versions()->where('version', $currentV - 1)->first();

        if(!$targetVersion){
            return null;
        }

        return $this->updateFile($file, [
            'storage_name' => $targetVersion->storage_name,
            'storage_path' => $targetVersion->storage_path,
            'mime_type' => $targetVersion->mime_type,
            'size' => $targetVersion->size,
            'hash' => $targetVersion->hash,
        ], logActivity: false);
    }

    public function restoreFrontVersion(File $file){
        $currentV = $file->versions()->max('version') ?? 1;
        $targetVersion = $file->versions()->where('version', $currentV + 1)->first();

        if(!$targetVersion){
            return null;
        }

        return $this->updateFile($file, [
            'storage_name' => $targetVersion->storage_name,
            'storage_path' => $targetVersion->storage_path,
            'mime_type' => $targetVersion->mime_type,
            'size' => $targetVersion->size,
            'hash' => $targetVersion->hash,
        ], logActivity: false);
    }

    public function hasVersionsInfo(File $file): array {
        $currentV = $file->versions()->max('version') ?? 1;

        return [
            'has_older' => $file->versions()->where('version', '<', $currentV)->exists(),
            'has_newer' => $file->versions()->where('version', '>', $currentV)->exists(),
            'current_version' => $currentV,
            'total_versions' => $file->versions()->count(),
        ];
    }
}
