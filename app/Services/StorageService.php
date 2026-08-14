<?php

use App\Models\Folder;
use App\Models\File;
use App\utils\ExceptionCustom\NombreDuplicadoException;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use App\utils\ExceptionCustom\CarpetaMovimientoPropioException;
use App\utils\ExceptionCustom\CarpetaCicloException;
use Illuminate\Support\Facades\DB;

class StorageService {

    public function addFolder(FolderDto $folder){
        return Folder::create($folder->toArray());
    }

    public function getFolderContent(string $userId, ?string $folderId = null){

        return [
            "folders" => Folder::where("user_id", $userId)
                ->when(
                    $folderId,
                    fn ($query) => $query->where("parent_id", $folderId),
                    fn ($query) => $query->whereNull("parent_id")
                )
                ->get(),
            "files" => File::where("user_id", $userId)
                ->when(
                    $folderId,
                    fn ($query) => $query->where("folder_id", $folderId),
                    fn ($query) => $query->whereNull("folder_id")
                )
                ->get()
        ];
    }
    
    public function getFolder(string $userId, string $folderId){
        return Folder::where("user_id", $userId)->where("id",$folderId)->firstOrFail();
    }

    public function getFile(string $userId, string $fileId){
        return File::where("user_id", $userId)->where("id",$fileId)->firstOrFail();
    }
    
    public function delete(string $userId, string $id, string $type = "folder"){
        return DB::transaction(function () use ($userId, $id, $type) {

            if (!in_array($type, ["folder", "file"])) {
                throw new InvalidArgumentException("Invalid element type");
            }

            $element = $type === "folder"
                ? Folder::where("user_id", $userId)
                    ->where("id", $id)
                    ->firstOrFail()
                : File::where("user_id", $userId)
                    ->where("id", $id)
                    ->firstOrFail();

            return $type === "folder"
                ? $this->moveFolderToTrash($element)
                : $this->moveFileToTrash($element);
        });
    }

    public function moveFolderToTrash(Folder $folder){
        $children = $folder->children()->get();
        $files = $folder->files()->get();

        $folder->delete();

        foreach ($children as $child) {
            $this->moveFolderToTrash($child);
        }

        foreach ($files as $file) {
            $file->delete();
        }

        return true;
    }
    public function moveFileToTrash(File $file){
        return $file->delete();
    }

    public function restoreFile(File $file){
        return DB::transaction(function () use ($file) {
            $folder = Folder::withTrashed()->where("id", $file->folder_id)->firstOrFail();
            $this->restoreParentFolders($folder);

            return $file->restore();
        });
    }

    public function restoreFolder(Folder $folder){
        return DB::transaction(function () use ($folder) {
            $this->restoreParentFolders($folder->parentWithTrashed()->first());

            $this->restoreFolderContents($folder);

            return true;
        });
    }
    
    private function restoreParentFolders(?Folder $folder){
        if (!$folder || !$folder->trashed()) {
            return;
        }

        $folder->restore();

        $parent = $folder->parentWithTrashed()->first();

        $this->restoreParentFolders($parent);
    }
    
    private function restoreFolderContents(Folder $folder){
        if ($folder->trashed()) {
            $folder->restore();
        }

        $folder->files()->onlyTrashed()->restore();

        $children = $folder->children()->onlyTrashed()->get();

        foreach ($children as $child) {
            $this->restoreFolderContents($child);
        }
    }

    public function getTrash(string $userId) : array {

        return [
            "folders" => Folder::onlyTrashed()->where("user_id",$userId)->get(),
            "files" => File::onlyTrashed()->where("user_id",$userId)->get()
        ];
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

    public function moveFolder(Folder $folder, ?string $folderId = null){
        if ($folderId !== null){
            if ($folderId === $folder->id){
                throw new CarpetaMovimientoPropioException();
            }

            $destination = Folder::where("id", $folderId)
                ->where("user_id", $folder->user_id)
                ->firstOrFail();

            if ($destination->trashed()){
                throw new CarpetaEliminadaException();
            }

            if ($this->isDescendant($folder, $folderId)){
                throw new CarpetaCicloException();
            }
        }

        $conflict = Folder::where("user_id", $folder->user_id)
            ->where("parent_id", $folderId)
            ->where("name", $folder->name)
            ->where("id", "!=", $folder->id)
            ->exists();

        if ($conflict){
            throw new NombreDuplicadoException("carpeta");
        }

        return $folder->update(["parent_id" => $folderId]);
    }

    private function isDescendant(Folder $folder, string $destinationFolderId): bool{
        $queue = [$folder->id];
        $visited = [];

        while (!empty($queue)){
            $currentId = array_shift($queue);
            if (in_array($currentId, $visited)) continue;
            $visited[] = $currentId;

            $children = Folder::where("parent_id", $currentId)
                ->where("user_id", $folder->user_id)
                ->pluck("id");

            foreach ($children as $childId){
                if ($childId === $destinationFolderId) return true;
                $queue[] = $childId;
            }
        }

        return false;
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

    public function renameFolder(Folder $folder, string $newName){
        $conflict = Folder::where("user_id", $folder->user_id)
                    ->where("parent_id", $folder->parent_id)
                    ->where("name", $newName)
                    ->where("id", "!=", $folder->id)
                    ->exists();
        if($conflict){
            throw new NombreDuplicadoException("carpeta");
        }
        return $folder->update(["name" => $newName]);
    }
}