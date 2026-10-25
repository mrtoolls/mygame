<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPromotion extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount_minor' => 'integer',
        'is_coin_mode' => 'boolean',
        'paid_at' => 'datetime',
        'approved_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
}
