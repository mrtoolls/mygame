<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyContest extends Model
{
    protected $table = 'ff_contests';
    protected $guarded = ['id'];
    protected $casts = [
        'entry_fee' => 'decimal:8',
        'fee_percent' => 'decimal:4',
        'sponsored_pool' => 'decimal:8',
        'final_pool' => 'decimal:8',
        'final_fee_burned' => 'decimal:8',
        'prize_structure' => 'array',
        'deadline_at' => 'datetime',
        'review_until_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function gameweek()
    {
        return $this->belongsTo(FantasyGameweek::class, 'gameweek_id');
    }

    public function entries()
    {
        return $this->hasMany(FantasyContestEntry::class, 'contest_id');
    }

    public function isDeadlinePassed(): bool
    {
        return now()->greaterThanOrEqualTo($this->deadline_at);
    }
}
