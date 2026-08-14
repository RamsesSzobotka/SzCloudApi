<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FileVersion extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = [
        "file_id",
        "version",
        "storage_name",
        "storage_path",
        "mime_type",
        "size",
        "hash",
    ];

    protected $hidden = [
        "hash",
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
