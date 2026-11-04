<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FantasyTeam extends Model
{
    protected $table = 'ff_teams';
    protected $guarded = ['id'];
    protected $casts = [
        'budget_remaining' => 'decimal:1',
        'fresh_start_active' => 'boolean',
        'one_week_reset_snapshot' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function season()
    {
        return $this->belongsTo(FantasySeason::class, 'season_id');
    }

    public function players()
    {
        return $this->hasMany(FantasyTeamPlayer::class, 'team_id');
    }

    public function startingPlayers()
    {
        return $this->players()->where('squad_role', 'starting');
    }

    public function benchPlayers()
    {
        return $this->players()->where('squad_role', 'bench')->orderBy('bench_order');
    }

    public function captain()
    {
        return $this->players()->where('is_captain', true);
    }

    public function viceCaptain()
    {
        return $this->players()->where('is_vice_captain', true);
    }

    public function gameweekHistory()
    {
        return $this->hasMany(FantasyTeamGameweekHistory::class, 'team_id');
    }

    public function chipUses()
    {
        return $this->hasMany(FantasyChipUse::class, 'team_id');
    }

    public function hasUsedChip(string $chipType): bool
    {
        return $this->chipUses()->where('chip_type', $chipType)->exists();
    }
}
