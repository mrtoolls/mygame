<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyTeamGameweekHistory extends Model
{
    protected $table = 'ff_team_gameweek_history';
    protected $guarded = ['id'];

    public function team()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_id');
    }

    public function gameweek()
    {
        return $this->belongsTo(FantasyGameweek::class, 'gameweek_id');
    }
}
