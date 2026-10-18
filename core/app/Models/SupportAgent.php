<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class SupportAgent extends Authenticatable
{
    protected $fillable = ['name', 'email', 'username', 'password', 'photo', 'title', 'bio', 'is_active', 'last_seen_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function conversations()
    {
        return $this->hasMany(SupportTicket::class);
    }
}
