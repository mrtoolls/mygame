<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoinListing extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['amount' => 'decimal:8'];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_user_id');
    }

    public function claimedByAgent()
    {
        return $this->belongsTo(SupportAgent::class, 'claimed_by_agent_id');
    }

    public function trade()
    {
        return $this->hasOne(CoinTrade::class, 'listing_id');
    }
}
