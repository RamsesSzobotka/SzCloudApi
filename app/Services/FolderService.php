<?php
namespace App\Services;
use App\Models\Folder;
use App\Models\File;
use App\Dtos\FolderDto;
use App\utils\ExceptionCustom\CarpetaEliminadaException;
use App\utils\ExceptionCustom\CarpetaMovimientoPropioException;
use App\utils\ExceptionCustom\CarpetaCicloException;
use Illuminate\Support\Facades\DB;

class FolderService {

    public function __construct(){}

    public function addFolder(FolderDto $folder){
        return DB::transaction(function () use ($folder) {
            $data = $folder->toArray();

            if ($data["parent_id"] !== null){
                Folder::where("id", $data["parent_id"])
                    ->where("user_id", $data["user_id"])
                    ->firstOrFail();
            }

            $existing = Folder::where("user_id", $data["user_id"])
                ->where("parent_id", $data["parent_id"])
                ->where("name", $data["name"])
                ->whereNull("deleted_at")
                ->first();

            if ($existing){
                return $existing;
            }

            return Folder::create($data);
        });
    }

    public function getFolderContent(string $userId, ?string $folderId = null, int $perPage = 10, int $page = 1){
        $folders = Folder::where("user_id", $userId)
            ->when(
                $folderId,
                fn ($query) => $query->where("parent_id", $folderId),
                fn ($query) => $query->whereNull("parent_id")
            )
            ->paginate($perPage, ['*'], 'page_folders', $page);

        $files = File::where("user_id", $userId)
            ->when(
                $folderId,
                fn ($query) => $query->where("folder_id", $folderId),
                fn ($query) => $query->whereNull("folder_id")
            )
            ->paginate($perPage, ['*'], 'page_files', $page);

        return [
            "folders" => $folders->items(),
            "files" => $files->items(),
            "pagination" => [
                "folders" => [
                    "current_page" => $folders->currentPage(),
                    "last_page" => $folders->lastPage(),
                    "per_page" => $folders->perPage(),
                    "total" => $folders->total(),
                ],
                "files" => [
                    "current_page" => $files->currentPage(),
                    "last_page" => $files->lastPage(),
                    "per_page" => $files->perPage(),
                    "total" => $files->total(),
                ],
            ]
        ];
    }

    public function getFolder(string $userId, string $folderId)
    {
        return Folder::where('user_id', $userId)->where('id', $folderId)->firstOrFail();
    }

    public function getTrashedFolder(string $userId, string $folderId){
        return Folder::withTrashed()->where("user_id", $userId)->where("id",$folderId)->firstOrFail();
    }

    public function moveFolderToTrash(Folder $folder){
        return DB::transaction(function () use ($folder) {
            $this->moveFolderToTrashRecursive($folder);
            return true;
        });
    }

    private function moveFolderToTrashRecursive(Folder $folder): void {
        $children = $folder->children()->get();
        $files = $folder->files()->get();

        $folder->delete();

        foreach ($children as $child) {
            $this->moveFolderToTrashRecursive($child);
        }

        foreach ($files as $file) {
            app(FileService::class)->moveFileToTrash($file);
        }
    }

    public function restoreFolder(Folder $folder){
        return DB::transaction(function () use ($folder) {
            $this->restoreParentFolders($folder->parentWithTrashed()->first());

            $conflict = Folder::where("user_id", $folder->user_id)
                ->where("parent_id", $folder->parent_id)
                ->where("name", $folder->name)
                ->where("id", "!=", $folder->id)
                ->first();

            if ($conflict) {
                $this->mergeFolders($folder, $conflict);
                return true;
            }

            $folder->restore();

            return true;
        });
    }

    public function restoreParentFolders(?Folder $folder){
        if (!$folder || !$folder->trashed()) {
            return;
        }

        $folder->restore();

        $parent = $folder->parentWithTrashed()->first();

        $this->restoreParentFolders($parent);
    }

    public function getTrash(string $userId) : array {

        return [
            "folders" => Folder::onlyTrashed()->where("user_id",$userId)->get(),
            "files" => File::onlyTrashed()->where("user_id",$userId)->get()
        ];
    }

    public function deleteTrash(string $userId){
        return DB::transaction(function () use($userId){
            $folderIds = Folder::withTrashed()->where("user_id",$userId)
                ->onlyTrashed()->pluck("id");

            foreach($folderIds as $folderId){
                $folder = Folder::withTrashed()->where("id",$folderId)->first();
                if($folder){
                    $this->deletePermanentFolderRecursive($folder);
                }
            }

            $fileIds = File::withTrashed()->where("user_id",$userId)
                ->onlyTrashed()->pluck("id");

            foreach($fileIds as $fileId){
                $file = File::withTrashed()->where("id",$fileId)->first();
                if($file){
                    app(FileService::class)->deletePermanentFile($file);
                }
            }
        });
    }

    public function deletePermanentFolderWithTransaction(Folder $folder){
        return DB::transaction(function () use ($folder) {
            $this->deletePermanentFolderRecursive($folder);
            return true;
        });
    }

    private function deletePermanentFolderRecursive(Folder $folder): void {
        $children = $folder->childrenWithTrashed()->get();
        $files = File::withTrashed()->where("folder_id", $folder->id)->get();

        foreach ($children as $child) {
            $this->deletePermanentFolderRecursive($child);
        }

        foreach ($files as $file) {
            app(FileService::class)->deletePermanentFile($file);
        }

        $folder->forceDelete();
    }

    public function getFolderContentCount(Folder $folder): int {
        $folders = $folder->childrenWithTrashed()->count();
        $files = File::withTrashed()->where("folder_id", $folder->id)->count();
        return $folders + $files;
    }

    public function mergeFolders(Folder $source, Folder $target): void {
        File::withTrashed()->where("folder_id", $source->id)->update(["folder_id" => $target->id]);

        $this->moveChildFolders($source->id, $target->id);

        $source->forceDelete();
    }

    private function moveChildFolders(string $sourceParentId, string $targetParentId): void {
        $children = Folder::withTrashed()->where("parent_id", $sourceParentId)->get();

        foreach ($children as $child) {
            $child->update(["parent_id" => $targetParentId]);
        }
    }

    public function checkFolderName(string $userId, ?string $parentId, string $name): array {
        $existing = Folder::where("user_id", $userId)
            ->where("parent_id", $parentId)
            ->where("name", $name)
            ->whereNull("deleted_at")
            ->first();

        if (!$existing) {
            return ["exists" => false, "conflicting_folder" => null];
        }

        return [
            "exists" => true,
            "conflicting_folder" => [
                "id" => $existing->id,
                "name" => $existing->name,
                "content_count" => $this->getFolderContentCount($existing),
            ],
        ];
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

        return DB::transaction(function () use ($folder, $folderId) {
            $conflictFolder = Folder::where("user_id", $folder->user_id)
                ->where("parent_id", $folderId)
                ->where("name", $folder->name)
                ->where("id", "!=", $folder->id)
                ->first();

            if ($conflictFolder){
                $sourceCount = $this->getFolderContentCount($folder);
                $targetCount = $this->getFolderContentCount($conflictFolder);

                if ($sourceCount <= $targetCount) {
                    $this->mergeFolders($folder, $conflictFolder);
                    return true;
                } else {
                    $this->mergeFolders($conflictFolder, $folder);
                    return $folder->update(["parent_id" => $folderId]);
                }
            }

            return $folder->update(["parent_id" => $folderId]);
        });
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

    public function renameFolder(Folder $folder, string $newName){
        return DB::transaction(function () use ($folder, $newName) {
            $conflict = Folder::where("user_id", $folder->user_id)
                        ->where("parent_id", $folder->parent_id)
                        ->where("name", $newName)
                        ->where("id", "!=", $folder->id)
                        ->first();

            if($conflict){
                $sourceCount = $this->getFolderContentCount($folder);
                $targetCount = $this->getFolderContentCount($conflict);

                if ($sourceCount <= $targetCount) {
                    $this->mergeFolders($folder, $conflict);
                    return true;
                } else {
                    $this->mergeFolders($conflict, $folder);
                    return $folder->update(["name" => $newName]);
                }
            }

            return $folder->update(["name" => $newName]);
        });
    }

    public function getFolderHierarchy(string $userId): array{
        $folders = Folder::where("user_id", $userId)
            ->whereNull("deleted_at")
            ->orderBy("name")
            ->get(["id", "parent_id", "name"]);

        $byParent = $folders->groupBy("parent_id");

        $build = function ($parentId) use ($byParent, &$build){
            $children = $byParent->get($parentId, collect());
            return $children->map(function ($f) use ($byParent, $build){
                return [
                    "id" => $f->id,
                    "name" => $f->name,
                    "children" => $build($f->id),
                ];
            })->values()->all();
        };

        return $build(null);
    }
}
