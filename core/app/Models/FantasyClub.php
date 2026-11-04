<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyClub extends Model
{
    protected $table = 'ff_clubs';
    protected $guarded = ['id'];
    protected $casts = ['strength_rating' => 'decimal:2', 'is_active' => 'boolean'];

    public function players()
    {
        return $this->hasMany(FantasyPlayer::class, 'club_id');
    }
}
