<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    protected $casts = ['metadata' => 'array', 'is_read' => 'boolean'];

    public function ticket(){
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id', 'id');
    }

    public function admin(){
        return $this->belongsTo(Admin::class, 'admin_id', 'id');
    }

    public function supportAgent()
    {
        return $this->belongsTo(SupportAgent::class, 'support_agent_id');
    }

    public function attachments()
    {
        return $this->hasMany(SupportAttachment::class,'support_message_id','id');
    }
}
