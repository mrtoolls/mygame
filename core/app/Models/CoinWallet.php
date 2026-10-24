<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinWallet extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'available_balance' => 'decimal:8',
        'held_balance' => 'decimal:8',
    ];

    public function entries()
    {
        return $this->hasMany(CoinLedgerEntry::class, 'wallet_id');
    }
}
