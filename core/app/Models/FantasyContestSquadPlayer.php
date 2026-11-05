<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyContestSquadPlayer extends Model
{
    protected $table = 'ff_contest_squad_players';
    protected $guarded = ['id'];
    protected $casts = [
        'purchase_price' => 'decimal:1',
        'is_captain' => 'boolean',
        'is_vice_captain' => 'boolean',
    ];

    public function squad()
    {
        return $this->belongsTo(FantasyContestSquad::class, 'contest_squad_id');
    }

    public function player()
    {
        return $this->belongsTo(FantasyPlayer::class, 'player_id');
    }
}
