<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'finalized_at' => 'datetime',
        'eligible_games' => 'array',
        'type_settings' => 'array',
    ];

    const TYPE_WAGER_RACE = 'wager_race';
    const TYPE_BIGGEST_WIN = 'biggest_win';
    const TYPE_LUCKY_DRAW = 'lucky_draw';
    const TYPE_TABLE_LEAGUE = 'table_league';

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_LIVE = 'live';
    const STATUS_ENDED = 'ended';
    const STATUS_FINALIZED = 'finalized';
    const STATUS_PAID = 'paid';

    // Fields that affect scoring/eligibility - locked once the competition is past draft/scheduled.
    const SCORING_LOCKED_FIELDS = [
        'type', 'type_settings', 'min_bet_usd', 'eligible_games',
        'max_games_per_opponent_pair', 'min_distinct_opponents', 'min_games',
        'ticket_usd_per_ticket', 'max_tickets_per_user', 'start_at', 'end_at',
    ];

    public function isScoringLocked(): bool
    {
        return !in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    public function prizes()
    {
        return $this->hasMany(CompetitionPrize::class)->orderBy('rank');
    }

    public function results()
    {
        return $this->hasMany(CompetitionResult::class)->orderBy('rank');
    }

    public function flags()
    {
        return $this->hasMany(CompetitionFlag::class);
    }

    public function gameEntries()
    {
        return $this->hasMany(CompetitionGameEntry::class);
    }

    public function pairCounts()
    {
        return $this->hasMany(CompetitionPairCount::class);
    }

    public function tickets()
    {
        return $this->hasMany(CompetitionTicket::class);
    }

    public function draws()
    {
        return $this->hasMany(CompetitionDraw::class)->orderBy('draw_position');
    }

    public function hasAnyPaidResult(): bool
    {
        return $this->results()->whereNotNull('paid_at')->exists();
    }
}
