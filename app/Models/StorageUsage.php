<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StorageUsage extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    public $incrementing = false;

    protected $fillable = [
        "user_id",
        "used_bytes",
        "file_count",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
