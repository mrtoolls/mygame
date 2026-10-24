<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTradeMessage extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['created_at' => 'datetime'];

    public function trade()
    {
        return $this->belongsTo(CoinTrade::class, 'trade_id');
    }
}
