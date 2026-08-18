<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sesion extends Model
{

    use HasUuids;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'refresh_token_hash',
        'ip_address',
        'user_agent',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public $hidden = [
        "refresh_token_hash"
    ];
    
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
                     ->where('expires_at', '>', now());
    }

    public function scopeWithinLifetime(Builder $query): Builder
    {
        return $query->where('created_at', '>', now()->subDays(30));
    }

    public function hasExceededLifetime(): bool
    {
        return $this->created_at->diffInDays(now()) >= 30;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
