<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyLeagueMember extends Model
{
    protected $table = 'ff_league_members';
    protected $guarded = ['id'];
    protected $casts = ['joined_at' => 'datetime'];

    public function league()
    {
        return $this->belongsTo(FantasyLeague::class, 'league_id');
    }

    public function team()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_id');
    }
}
