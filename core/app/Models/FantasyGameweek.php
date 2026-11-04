<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyGameweek extends Model
{
    protected $table = 'ff_gameweeks';
    protected $guarded = ['id'];
    protected $casts = [
        'deadline_at' => 'datetime',
        'kickoff_start_at' => 'datetime',
        'kickoff_end_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function season()
    {
        return $this->belongsTo(FantasySeason::class, 'season_id');
    }

    public function fixtures()
    {
        return $this->hasMany(FantasyFixture::class, 'gameweek_id');
    }

    public function isDeadlinePassed(): bool
    {
        return now()->greaterThanOrEqualTo($this->deadline_at);
    }
}
