<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyContestSquad extends Model
{
    protected $table = 'ff_contest_squads';
    protected $guarded = ['id'];
    protected $casts = [
        'budget_tier' => 'decimal:1',
        'budget_remaining' => 'decimal:1',
    ];

    public function contest()
    {
        return $this->belongsTo(FantasyContest::class, 'contest_id');
    }

    public function players()
    {
        return $this->hasMany(FantasyContestSquadPlayer::class, 'contest_squad_id');
    }
}
