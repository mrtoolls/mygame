<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionDraw extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'seed_info' => 'array',
        'drawn_at' => 'datetime',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}
