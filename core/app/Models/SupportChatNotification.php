<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportChatNotification extends Model
{
    protected $fillable = ['recipient_type', 'recipient_id', 'support_ticket_id', 'support_message_id', 'title', 'body', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
