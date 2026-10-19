<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPromotionRate extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'daily_price_minor' => 'integer',
        'weekly_price_minor' => 'integer',
        'monthly_price_minor' => 'integer',
    ];

    public function currency() { return $this->belongsTo(Currency::class); }
}
