<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinVendor extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'is_selling' => 'boolean',
        'sell_rate' => 'decimal:8',
        'per_trade_limit' => 'decimal:8',
        'daily_limit' => 'decimal:8',
        'bond_amount' => 'decimal:8',
        'bond_release_after_at' => 'datetime',
        'resigned_at' => 'datetime',
        'reliability_score' => 'decimal:2',
        'encrypted_payment_methods' => 'encrypted:array',
        'linked_user_ids' => 'encrypted:array',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function application() { return $this->belongsTo(CoinVendorApplication::class, 'application_id'); }
    public function currency() { return $this->belongsTo(Currency::class); }

    public function scopeActive($query) { return $query->where('status', 'active'); }
}
