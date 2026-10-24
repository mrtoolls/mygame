<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentCoinFloatGrant extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:8',
        'decided_at' => 'datetime',
    ];

    public function agent()
    {
        return $this->belongsTo(SupportAgent::class, 'support_agent_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(Admin::class, 'requested_by_admin_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
