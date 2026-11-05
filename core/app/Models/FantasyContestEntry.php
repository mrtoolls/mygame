<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyContestEntry extends Model
{
    protected $table = 'ff_contest_entries';
    protected $guarded = ['id'];
    protected $casts = [
        'stake' => 'decimal:8',
        'payout' => 'decimal:8',
        'entered_at' => 'datetime',
    ];

    public function contest()
    {
        return $this->belongsTo(FantasyContest::class, 'contest_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_id');
    }

    public function contestSquad()
    {
        return $this->belongsTo(FantasyContestSquad::class, 'contest_squad_id');
    }
}
