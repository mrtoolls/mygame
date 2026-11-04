<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyTransfer extends Model
{
    protected $table = 'ff_transfers';
    protected $guarded = ['id'];
    protected $casts = ['is_free' => 'boolean', 'sell_price' => 'decimal:1', 'buy_price' => 'decimal:1'];

    public function team()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_id');
    }

    public function playerOut()
    {
        return $this->belongsTo(FantasyPlayer::class, 'player_out_id');
    }

    public function playerIn()
    {
        return $this->belongsTo(FantasyPlayer::class, 'player_in_id');
    }
}
