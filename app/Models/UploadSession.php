<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = [
        "user_id",
        "folder_id",
        "file_name",
        "mime_type",
        "total_size",
        "uploaded_size",
        "storage_path",
        "status",
        "expires_at",
    ];

    protected function casts(): array
    {
        return [
            "expires_at" => "datetime",
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }
}
