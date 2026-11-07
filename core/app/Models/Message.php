<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $table = 'conversation_messages';
    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
