<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expansion extends Model
{
    public $incrementing = true;
    protected $keyType = 'integer';

    protected $fillable = [
        "name",
        "storage_bytes",
        "price_cents",
    ];

    public function userExpansions()
    {
        return $this->hasMany(UserExpansion::class);
    }
}
