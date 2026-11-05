<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyDuelSeat extends Model
{
    protected $table = 'ff_duel_seats';
    protected $guarded = ['id'];
    protected $casts = ['ready_at' => 'datetime'];
}
