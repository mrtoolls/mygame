<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyPlayerFixtureStat extends Model
{
    protected $table = 'ff_player_fixture_stats';
    protected $guarded = ['id'];
    protected $casts = ['clean_sheet' => 'boolean', 'started' => 'boolean'];

    public function fixture()
    {
        return $this->belongsTo(FantasyFixture::class, 'fixture_id');
    }

    public function player()
    {
        return $this->belongsTo(FantasyPlayer::class, 'player_id');
    }
}
