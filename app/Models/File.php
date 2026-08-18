<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasUuids, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        "user_id",
        "folder_id",
        "original_name",
        "storage_name",
        "storage_path",
        "mime_type",
        "extension",
        "size",
        "hash",
    ];

    protected $hidden = [
        "hash",
    ];

    protected function casts(): array
    {
        return [
            "deleted_at" => "datetime",
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class, "folder_id");
    }

    public function versions()
    {
        return $this->hasMany(FileVersion::class);
    }

    public function permissions()
    {
        return $this->hasMany(FilePermission::class);
    }

    public function shareLinks()
    {
        return $this->hasMany(ShareLink::class);
    }
}