<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        "user_id",
        "action",
        "resource_type",
        "resource_id",
        "ip_address",
        "user_agent",
        "metadata",
        "created_at",
    ];

    protected function casts(): array
    {
        return [
            "metadata" => "array",
            "created_at" => "datetime",
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
