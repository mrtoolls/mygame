<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyDuelSquad extends Model
{
    protected $table = 'ff_duel_squads';
    protected $guarded = ['id'];
    protected $casts = ['player_ids' => 'array', 'auto_submitted' => 'boolean', 'submitted_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
