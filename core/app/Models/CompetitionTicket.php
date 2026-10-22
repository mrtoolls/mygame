<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionTicket extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'frozen_at' => 'datetime',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
