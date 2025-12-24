<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    use HasUuids;

    protected $fillable = [
        'display_name',
        'auth_provider',
        'auth_subject',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }
}
