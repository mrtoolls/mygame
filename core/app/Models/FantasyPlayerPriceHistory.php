<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyPlayerPriceHistory extends Model
{
    protected $table = 'ff_player_price_history';
    protected $guarded = ['id'];
    protected $casts = ['old_price' => 'decimal:1', 'new_price' => 'decimal:1'];

    public function player()
    {
        return $this->belongsTo(FantasyPlayer::class, 'player_id');
    }
}
