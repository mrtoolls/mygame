<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableGameMessage extends Model
{
    protected $guarded = ['id'];

    public function user() { return $this->belongsTo(User::class); }
    public function reactions() { return $this->hasMany(TableGameMessageReaction::class, 'message_id'); }
    public function replyTo() { return $this->belongsTo(self::class, 'reply_to_id'); }
    public function reporter() { return $this->belongsTo(User::class, 'reported_by'); }
}
