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
}
