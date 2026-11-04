<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyTeamPlayer extends Model
{
    protected $table = 'ff_team_players';
    protected $guarded = ['id'];
    protected $casts = [
        'purchase_price' => 'decimal:1',
        'is_captain' => 'boolean',
        'is_vice_captain' => 'boolean',
    ];

    public function team()
    {
        return $this->belongsTo(FantasyTeam::class, 'team_id');
    }

    public function player()
    {
        return $this->belongsTo(FantasyPlayer::class, 'player_id');
    }

    /** Selling price: purchase price plus half the gain over current market price, rounded down to the nearest 0.1. */
    public function sellingPrice(): string
    {
        $current = (string) $this->player->price;
        $purchase = (string) $this->purchase_price;
        if (bccomp($current, $purchase, 1) <= 0) return $current;
        $gain = bcsub($current, $purchase, 2);
        $halfGain = bcdiv($gain, '2', 2);
        $sell = bcadd($purchase, $halfGain, 2);
        // Round down to the nearest 0.1 - bcdiv truncates (floors, for positive values) toward
        // zero at the given scale, so dividing by 0.1 and multiplying back is an exact tenths-floor.
        $tenths = bcdiv($sell, '0.1', 0);
        return bcmul($tenths, '0.1', 1);
    }
}
