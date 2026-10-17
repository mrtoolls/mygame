<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VipRakebackClaim extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tier()
    {
        return $this->belongsTo(VipTier::class, 'vip_tier_id');
    }
}
