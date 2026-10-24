<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'buy_margin_percent' => 'decimal:4',
        'sell_margin_percent' => 'decimal:4',
        'buy_price' => 'decimal:8',
        'sell_price' => 'decimal:8',
        'last_computed_at' => 'datetime',
        'flagged_for_review' => 'boolean',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function logs()
    {
        return $this->hasMany(CoinRateLog::class, 'currency_id', 'currency_id')->latest();
    }
}
