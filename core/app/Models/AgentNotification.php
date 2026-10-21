<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentNotification extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function deposit()
    {
        return $this->belongsTo(Deposit::class);
    }

    public function scopeUnreadFor($query, string $type, $id)
    {
        return $query->where('recipient_type', $type)->where('recipient_id', $id)->whereNull('read_at');
    }
}
