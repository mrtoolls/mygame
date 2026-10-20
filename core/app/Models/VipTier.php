<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class VipTier extends Model
{
    use GlobalStatus;

    protected $guarded = ['id'];

    public function claims()
    {
        return $this->hasMany(VipRakebackClaim::class);
    }

    public function badgeImageUrl(): ?string
    {
        return $this->badge_image ? getImage('assets/images/vip-tiers/' . $this->badge_image) : null;
    }
}
