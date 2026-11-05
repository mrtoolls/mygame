<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyDuel extends Model
{
    protected $table = 'ff_duels';
    protected $guarded = ['id'];
    protected $casts = [
        'slate' => 'array', 'ready_users' => 'array', 'result' => 'array',
        'ready_started_at' => 'datetime', 'ready_deadline_at' => 'datetime',
        'build_started_at' => 'datetime', 'build_deadline_at' => 'datetime',
        'live_started_at' => 'datetime', 'live_ends_at' => 'datetime', 'seed_revealed_at' => 'datetime',
    ];
}
