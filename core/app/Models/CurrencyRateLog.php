<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRateLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'old_rate_to_usd' => 'decimal:12',
        'new_rate_to_usd' => 'decimal:12',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
