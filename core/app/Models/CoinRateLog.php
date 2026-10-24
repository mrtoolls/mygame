<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinRateLog extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected $casts = [
        'old_buy_price' => 'decimal:8',
        'new_buy_price' => 'decimal:8',
        'old_sell_price' => 'decimal:8',
        'new_sell_price' => 'decimal:8',
        'change_percent' => 'decimal:4',
        'created_at' => 'datetime',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
