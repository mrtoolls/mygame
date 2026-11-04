<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyFixture extends Model
{
    protected $table = 'ff_fixtures';
    protected $guarded = ['id'];
    protected $casts = [
        'kickoff_at' => 'datetime',
        'simulated_at' => 'datetime',
    ];

    public function gameweek()
    {
        return $this->belongsTo(FantasyGameweek::class, 'gameweek_id');
    }

    public function homeClub()
    {
        return $this->belongsTo(FantasyClub::class, 'home_club_id');
    }

    public function awayClub()
    {
        return $this->belongsTo(FantasyClub::class, 'away_club_id');
    }

    public function events()
    {
        return $this->hasMany(FantasyFixtureEvent::class, 'fixture_id')->orderBy('minute');
    }

    public function playerStats()
    {
        return $this->hasMany(FantasyPlayerFixtureStat::class, 'fixture_id');
    }

    public function isKickedOff(): bool
    {
        return now()->greaterThanOrEqualTo($this->kickoff_at);
    }

    public function minuteDuration(): int
    {
        return (int) (gs('ff_match_duration_minutes') ?: 10);
    }

    /** Elapsed match-clock minutes (0-90+) based purely on wall-clock time since kickoff - no background process runs the match. */
    public function elapsedMatchMinutes(): int
    {
        if (!$this->isKickedOff()) return 0;
        $realMinutesElapsed = $this->kickoff_at->diffInSeconds(now()) / 60;
        $ratio = $realMinutesElapsed / max(1, $this->minuteDuration());
        return (int) min(90, floor($ratio * 90));
    }

    public function isFullTime(): bool
    {
        return $this->isKickedOff() && $this->kickoff_at->diffInSeconds(now()) / 60 >= $this->minuteDuration();
    }
}
