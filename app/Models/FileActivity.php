<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FileActivity extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        "file_id",
        "action",
        "old_values",
        "new_values",
        "is_undone",
    ];

    protected $casts = [
        "old_values" => "array",
        "new_values" => "array",
        "is_undone" => "boolean",
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }
}
