<?php
namespace App\Services;

use App\Models\Folder;
use App\Models\File;
use App\Models\User;
use App\utils\ExceptionCustom\NombreDuplicadoException;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;

class FileService {

    public function __construct(
        private StorageUsageService $storageUsageService,
        private FolderService $folderService,
    ){}

    public function addFile(string $userId, UploadedFile $file, ?string $folderId = null){
        return DB::transaction(function () use ($userId, $file, $folderId) {

            $user = User::findOrFail($userId);
            $fileSize = $file->getSize();

            if (!$this->storageUsageService->storageVerify($user, $fileSize)) {
                throw new \App\utils\ExceptionCustom\StorageException("No tienes suficiente espacio de almacenamiento");
            }

            if ($folderId !== null) {
                Folder::where("id", $folderId)
                    ->where("user_id", $userId)
                    ->firstOrFail();
            }

            $conflictQuery = File::where("user_id", $userId)
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
            $storagePath = "users/{$userId}/files/{$storageName}";
            $hash = hash_file('sha256', $file->getRealPath());

            Storage::disk('minio')->put($storagePath, file_get_contents($file->getRealPath()));

            $fileRecord = File::create([
                "user_id" => $userId,
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

    public function deletePermanentFile(File $file){
        Storage::disk('minio')->delete($file->storage_path);

        $user = User::findOrFail($file->user_id);
        $this->storageUsageService->deleteFile($user, $file->size);

        return $file->forceDelete();
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

        if ($conflict){
            throw new NombreDuplicadoException("archivo");
        }

        return $file->update(["folder_id" => $folderId]);
    }

    public function renameFile(File $file, string $newName){
        $conflict = File::where("user_id", $file->user_id)
                    ->where("folder_id", $file->folder_id)
                    ->where("original_name", $newName)
                    ->where("id", "!=", $file->id)
                    ->exists();

        if($conflict){
            throw new NombreDuplicadoException("archivo");
        }
        return $file->update(["original_name" => $newName]);
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

}
