<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FolderPermission extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    const EDITOR = 'editor';
    const VIEWER = 'viewer';

    protected $fillable = [
        'folder_id',
        'user_id',
        'permission',
    ];

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
