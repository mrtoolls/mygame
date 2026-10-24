<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPenalty extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'percent' => 'decimal:4',
        'coin_amount' => 'decimal:8',
        'split_to_wronged_party' => 'decimal:8',
        'split_to_platform' => 'decimal:8',
    ];

    public function trade()
    {
        return $this->belongsTo(CoinTrade::class, 'trade_id');
    }
}
