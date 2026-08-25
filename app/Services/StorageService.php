<?php
namespace App\Services;
use App\Models\Folder;
use App\Models\File;
use App\Dtos\FolderDto;
use Illuminate\Http\UploadedFile;

//@deprecated
class StorageService {

    public function __construct(
        private FileService $fileService,
        private FolderService $folderService,
    ){}

    public function addFolder(FolderDto $folder){
        return $this->folderService->addFolder($folder);
    }

    public function addFile(string $userId, UploadedFile $file, ?string $folderId = null){
        return $this->fileService->addFile($userId, $file, $folderId);
    }

    public function getFolderContent(string $userId, ?string $folderId = null, int $perPage = 10, int $page = 1){
        return $this->folderService->getFolderContent($userId, $folderId, $perPage, $page);
    }

    public function getFolder(string $userId, string $folderId){
        return $this->folderService->getFolder($userId, $folderId);
    }

    public function getFile(string $userId, string $fileId){
        return $this->fileService->getFile($userId, $fileId);
    }

    public function getTrashedFolder(string $userId, string $folderId){
        return $this->folderService->getTrashedFolder($userId, $folderId);
    }

    public function getTrashedFile(string $userId, string $fileId){
        return $this->fileService->getTrashedFile($userId, $fileId);
    }

    public function delete(string $userId, string $id, string $type = "folder"){
        if ($type === 'folder') {
            $folder = $this->folderService->getFolder($userId, $id);
            return $this->folderService->moveFolderToTrash($folder);
        }
        $file = $this->fileService->getFile($userId, $id);
        return $this->fileService->moveFileToTrash($file);
    }

    public function moveFolderToTrash(Folder $folder){
        return $this->folderService->moveFolderToTrash($folder);
    }

    public function moveFileToTrash(File $file){
        return $this->fileService->moveFileToTrash($file);
    }

    public function restoreFile(File $file){
        return $this->fileService->restoreFile($file);
    }

    public function restoreFolder(Folder $folder){
        return $this->folderService->restoreFolder($folder);
    }

    public function getTrash(string $userId) : array {
        return $this->folderService->getTrash($userId);
    }

    public function deleteTrash(string $userId){
        return $this->folderService->deleteTrash($userId);
    }

    public function deletePermanent(string $userId, string $id, string $type = "folder"){
        if ($type === 'folder') {
            $folder = $this->folderService->getTrashedFolder($userId, $id);
            return $this->folderService->deletePermanentFolderWithTransaction($folder);
        }
        $file = $this->fileService->getTrashedFile($userId, $id);
        return $this->fileService->deletePermanentFile($file);
    }

    public function deletePermanentFolder(Folder $folder){
        return $this->folderService->deletePermanentFolderWithTransaction($folder);
    }

    public function deletePermanentFile(File $file){
        return $this->fileService->deletePermanentFile($file);
    }

    public function moveFile(File $file, ?string $folderId = null){
        return $this->fileService->moveFile($file, $folderId);
    }

    public function moveFolder(Folder $folder, ?string $folderId = null){
        return $this->folderService->moveFolder($folder, $folderId);
    }

    public function renameFile(File $file, string $newName){
        return $this->fileService->renameFile($file, $newName);
    }

    public function renameFolder(Folder $folder, string $newName){
        return $this->folderService->renameFolder($folder, $newName);
    }

    public function urlDownloadFile(File $file){
        return $this->fileService->urlDownloadFile($file);
    }
}
