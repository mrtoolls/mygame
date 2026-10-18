<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class BonusCode extends Model
{
    use GlobalStatus;

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function redemptions()
    {
        return $this->hasMany(BonusCodeRedemption::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }
}
