<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'allowed_payment_methods' => 'array',
        'min_deposit_minor' => 'integer',
        'max_deposit_minor' => 'integer',
        'min_withdrawal_minor' => 'integer',
        'max_withdrawal_minor' => 'integer',
        'min_table_stake_minor' => 'integer',
        'max_table_stake_minor' => 'integer',
        'is_allowed' => 'boolean',
        'is_enabled' => 'boolean',
        'coin_marketplace_enabled' => 'boolean',
        'vendors_allowed' => 'boolean',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
