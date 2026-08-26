<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Folder extends Model
{
    use HasUuids, SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        "user_id",
        "parent_id",
        "name",
        "is_system",
    ];

    protected function casts(): array
    {
        return [
            "deleted_at" => "datetime",
            "is_system" => "boolean",
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Folder::class, "parent_id");
    }

    public function children()
    {
        return $this->hasMany(Folder::class, "parent_id");
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function parentWithTrashed()
    {
        return $this->belongsTo(Folder::class, "parent_id")->withTrashed();
    }

    public function childrenWithTrashed(){
        return $this->hasMany(Folder::class, "parent_id")->withTrashed();
    }

    public function permissions()
    {
        return $this->hasMany(FolderPermission::class);
    }

    public function sharedWith()
    {
        return $this->belongsToMany(User::class, 'folder_permissions')
            ->withPivot('permission');
    }
}