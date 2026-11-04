<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyChipUse extends Model
{
    protected $table = 'ff_chip_uses';
    protected $guarded = ['id'];
    protected $casts = ['used_at' => 'datetime', 'reverted_at' => 'datetime'];

    public function team()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_id');
    }

    public function gameweek()
    {
        return $this->belongsTo(FantasyGameweek::class, 'gameweek_id');
    }
}
