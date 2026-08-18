<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShareLink extends Model
{
    use HasUuids;
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        "file_id",
        "token_hash",
        "expires_at",
        "max_downloads",
        "download_count",
        "password_hash",
        "revoked_at",
    ];

    protected $hidden = [
        "token_hash",
        "password_hash",
    ];

    protected function casts(): array
    {
        return [
            "expires_at" => "datetime",
            "revoked_at" => "datetime",
        ];
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_downloads !== null && $this->download_count >= $this->max_downloads) {
            return false;
        }

        return true;
    }
}
