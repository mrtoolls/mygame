<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinTrade extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'amount' => 'decimal:8',
        'fee_amount' => 'decimal:8',
        'payment_deadline_at' => 'datetime',
        'payment_claimed_at' => 'datetime',
        'confirmation_deadline_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'dispute_opened_at' => 'datetime',
        'no_payment_ruled_at' => 'datetime',
    ];

    public function listing()
    {
        return $this->belongsTo(CoinListing::class, 'listing_id');
    }

    public function events()
    {
        return $this->hasMany(CoinTradeEvent::class, 'trade_id')->orderBy('created_at');
    }

    public function messages()
    {
        return $this->hasMany(CoinTradeMessage::class, 'trade_id')->orderBy('created_at');
    }

    public function penalties()
    {
        return $this->hasMany(CoinPenalty::class, 'trade_id');
    }

    public function isParticipant(string $type, int $id): bool
    {
        return ($this->coin_seller_type === $type && (int) $this->coin_seller_id === $id)
            || ($this->coin_buyer_type === $type && (int) $this->coin_buyer_id === $id);
    }
}
