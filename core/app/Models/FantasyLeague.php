<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyLeague extends Model
{
    protected $table = 'ff_leagues';
    protected $guarded = ['id'];

    public function season()
    {
        return $this->belongsTo(FantasySeason::class, 'season_id');
    }

    public function members()
    {
        return $this->hasMany(FantasyLeagueMember::class, 'league_id');
    }

    public function h2hFixtures()
    {
        return $this->hasMany(FantasyH2hFixture::class, 'league_id');
    }
}
