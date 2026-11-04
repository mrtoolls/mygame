<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyH2hFixture extends Model
{
    protected $table = 'ff_h2h_fixtures';
    protected $guarded = ['id'];

    public function league()
    {
        return $this->belongsTo(FantasyLeague::class, 'league_id');
    }

    public function gameweek()
    {
        return $this->belongsTo(FantasyGameweek::class, 'gameweek_id');
    }

    public function teamA()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_a_id');
    }

    public function teamB()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_b_id');
    }
}
