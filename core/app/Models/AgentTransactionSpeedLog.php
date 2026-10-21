<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentTransactionSpeedLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'rated_at' => 'datetime',
        'met_fast_threshold' => 'boolean',
    ];

    public function agent()
    {
        return $this->belongsTo(SupportAgent::class, 'support_agent_id');
    }

    public function deposit()
    {
        return $this->belongsTo(Deposit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
