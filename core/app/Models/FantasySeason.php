<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasySeason extends Model
{
    protected $table = 'ff_seasons';
    protected $guarded = ['id'];

    public function gameweeks()
    {
        return $this->hasMany(FantasyGameweek::class, 'season_id');
    }

    public function currentGameweek()
    {
        return $this->belongsTo(FantasyGameweek::class, 'current_gameweek_id');
    }
}
