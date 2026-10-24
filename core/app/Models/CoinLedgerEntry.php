<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinLedgerEntry extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:8',
        'available_balance_after' => 'decimal:8',
        'held_balance_after' => 'decimal:8',
        'created_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(CoinWallet::class, 'wallet_id');
    }
}
