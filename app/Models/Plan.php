<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        "name",
        "storage_limit",
        "max_file_size",
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
