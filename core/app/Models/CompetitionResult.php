<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionResult extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'paid_at' => 'datetime',
        'disqualified_at' => 'datetime',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
