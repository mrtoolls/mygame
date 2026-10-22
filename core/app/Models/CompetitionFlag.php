<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionFlag extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'context' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function relatedUser()
    {
        return $this->belongsTo(User::class, 'related_user_id');
    }
}
