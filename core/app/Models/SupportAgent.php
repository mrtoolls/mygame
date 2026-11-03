<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class SupportAgent extends Authenticatable
{
    protected $fillable = ['name', 'email', 'username', 'password', 'photo', 'title', 'bio', 'payment_details', 'contact_methods', 'is_active', 'is_payment_agent', 'last_seen_at', 'accepted_payment_types', 'is_verified', 'speed_override', 'is_moderator', 'is_marketplace_supervisor', 'is_selling_coins', 'is_chat_agent', 'is_news_editor', 'user_id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_payment_agent' => 'boolean',
        'last_seen_at' => 'datetime',
        'accepted_payment_types' => 'array',
        'payment_details' => 'array',
        'contact_methods' => 'array',
        'is_verified' => 'boolean',
        'is_moderator' => 'boolean',
        'is_marketplace_supervisor' => 'boolean',
        'is_selling_coins' => 'boolean',
        'is_chat_agent' => 'boolean',
        'is_news_editor' => 'boolean',
    ];

    public function scopeModerators($query)
    {
        return $query->where('is_active', true)->where('is_moderator', true);
    }

    public function conversations()
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function speedLogs()
    {
        return $this->hasMany(AgentTransactionSpeedLog::class, 'support_agent_id');
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class, 'support_agent_id');
    }

    public function scopePaymentAgents($query)
    {
        return $query->where('is_active', true)->where('is_payment_agent', true);
    }
}
