<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyFixtureEvent extends Model
{
    protected $table = 'ff_fixture_events';
    protected $guarded = ['id'];

    public function fixture()
    {
        return $this->belongsTo(FantasyFixture::class, 'fixture_id');
    }

    public function player()
    {
        return $this->belongsTo(FantasyPlayer::class, 'player_id');
    }

    public function secondaryPlayer()
    {
        return $this->belongsTo(FantasyPlayer::class, 'secondary_player_id');
    }
}
