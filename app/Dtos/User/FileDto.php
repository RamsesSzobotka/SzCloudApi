<?php
namespace App\Dtos\User;

class FileDto
{
    private string $user_id;
    private ?string $folder_id;

    private string $original_name;
    private string $storage_name;
    private string $storage_path;

    private string $mime_type;
    private string $extension;
    private int $size;
    private string $hash;

    public function __construct(
        string $user_id,
        ?string $folder_id,
        string $original_name,
        string $storage_name,
        string $storage_path,
        string $mime_type,
        string $extension,
        int $size,
        string $hash
    ) {
        $this->user_id = $user_id;
        $this->folder_id = $folder_id;
        $this->original_name = $original_name;
        $this->storage_name = $storage_name;
        $this->storage_path = $storage_path;
        $this->mime_type = $mime_type;
        $this->extension = $extension;
        $this->size = $size;
        $this->hash = $hash;
    }

    public function toArray(): array
    {
        return [
            "user_id" => $this->user_id,
            "folder_id" => $this->folder_id,
            "original_name" => $this->original_name,
            "storage_name" => $this->storage_name,
            "storage_path" => $this->storage_path,
            "mime_type" => $this->mime_type,
            "extension" => $this->extension,
            "size" => $this->size,
            "hash" => $this->hash
        ];
    }
}