<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTradeEvent extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function trade()
    {
        return $this->belongsTo(CoinTrade::class, 'trade_id');
    }
}
