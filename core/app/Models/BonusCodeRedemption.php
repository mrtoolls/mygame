<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusCodeRedemption extends Model
{
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bonusCode()
    {
        return $this->belongsTo(BonusCode::class);
    }
}
