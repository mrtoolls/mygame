<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class SupportAgent extends Authenticatable
{
    protected $fillable = ['name', 'email', 'username', 'password', 'photo', 'title', 'bio', 'payment_details', 'is_active', 'is_payment_agent', 'last_seen_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_payment_agent' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function conversations()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function scopePaymentAgents($query)
    {
        return $query->where('is_active', true)->where('is_payment_agent', true);
    }
}
