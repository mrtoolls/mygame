<?php

namespace App\Services\Fantasy;

use App\Games\TableGames\TableGameService;
use App\Models\FantasyClub;
use App\Models\FantasyDuel;
use App\Models\FantasyDuelSeat;
use App\Models\FantasyDuelSquad;
use App\Models\FantasyPlayer;
use App\Models\TableGame;
use App\Models\TableGameCommission;
use App\Models\TableGamePlayer;
use App\Models\TableGameSetting;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\CoinLedger;
use App\Support\DeterministicRandom;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/** Server-clock-driven fantasy matches layered on the existing coin-held table lifecycle. */
class FantasyDuelService
{
    public function __construct(
        private readonly TableGameService $tables,
        private readonly MatchSimulator $simulator,
        private readonly FantasyScoring $scoring,
        private readonly FantasyContestTierSettings $tiers,
    ) {}

    public function create(User $user, int $playerLimit, string $stake, float $tier, string $key, ?string $ip): TableGame
    {
        $existing = TableGame::where('create_idempotency_key', $key)->first();
        if ($existing) {
            if ((int) $existing->creator_id !== (int) $user->id) throw new InvalidArgumentException('That request key is already in use.');
            if ($existing->game_type !== 'fantasy_duel') throw new InvalidArgumentException('That request key was already used for a different game.');
            if (FantasyDuel::where('table_game_id', $existing->id)->exists()) return $existing->fresh(['players.user']);
            throw new InvalidArgumentException('That duel request is incomplete. Please create a new duel with a fresh request key.');
        }
        if (!gs('coin_mode')) throw new InvalidArgumentException('Live Duels use coin stakes. Enable Coin Mode before creating a duel.');
        if (!$this->tiers->isConfiguredTier($tier)) throw new InvalidArgumentException('Choose one of the currently configured budget tiers.');
        if (bccomp($stake, (string) $this->tiers->minimumFee($tier), 8) < 0) throw new InvalidArgumentException('The stake is below the minimum for this budget tier.');
        return DB::transaction(function () use ($user, $playerLimit, $stake, $tier, $key, $ip) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->assertDailyCap($user, $stake);
            $table = $this->tables->create($user, 'fantasy_duel', $playerLimit, $stake, $key);
            if (FantasyDuel::where('table_game_id', $table->id)->exists()) return $table;
            $seed = bin2hex(random_bytes(32));
            $slate = $this->createSlate($seed);
            $minutes = max(1, min(60, (int) (gs('ff_duel_lobby_minutes') ?: 10)));
            $table->join_deadline_at = now()->addMinutes($minutes);
            $table->settings = array_merge((array) $table->settings, ['commission_percent' => (float) (gs('ff_duel_fee_percent') ?: 5)]);
            $table->turn_deadline_at = null;
            $table->save();
            FantasyDuel::create([
                'table_game_id' => $table->id, 'budget_tier' => $tier, 'phase' => 'lobby',
                'seed_commitment' => hash('sha256', $seed), 'sealed_seed' => Crypt::encryptString($seed),
                'slate' => $slate,
            ]);
            FantasyDuelSeat::create(['table_game_id' => $table->id, 'user_id' => $user->id, 'entry_ip' => $ip]);
            $this->audit($table->id, $user->id, 'duel_created', ['tier' => $tier, 'players' => $playerLimit, 'seed_commitment' => hash('sha256', $seed)]);
            return $table->fresh(['players.user']);
        }, 3);
    }

    /** Run before the existing framework seats a joiner; both checks are repeated under the table lock. */
    public function join(User $user, string $tableId, ?string $ip): TableGame
    {
        return DB::transaction(function () use ($user, $tableId, $ip) {
            $table = TableGame::whereKey($tableId)->lockForUpdate()->firstOrFail();
            $duel = FantasyDuel::where('table_game_id', $table->id)->first();
            if (!$duel) return $this->tables->join($user, $tableId);
            if (TableGamePlayer::where('table_game_id', $tableId)->where('user_id', $user->id)->exists()) return $this->tables->join($user, $tableId);
            if ($duel->phase !== 'lobby') throw new InvalidArgumentException('This duel has moved beyond its lobby.');
            $this->assertCanPair($table, $user, $ip);
            $this->assertDailyCap($user, (string) $table->stake);
            $joined = $this->tables->join($user, $tableId);
            FantasyDuelSeat::firstOrCreate(['table_game_id' => $table->id, 'user_id' => $user->id], ['entry_ip' => $ip]);
            $this->flagRepeatOpponents($table, $user);
            return $joined;
        }, 3);
    }

    public function acceptInvite(User $user, int $inviteId, ?string $ip): TableGame
    {
        return DB::transaction(function () use ($user, $inviteId, $ip) {
            $invite = \App\Models\TableGameInvite::whereKey($inviteId)->lockForUpdate()->firstOrFail();
            $table = TableGame::whereKey($invite->table_game_id)->lockForUpdate()->firstOrFail();
            $duel = FantasyDuel::where('table_game_id', $table->id)->first();
            $alreadySeated = TableGamePlayer::where('table_game_id', $table->id)->where('user_id', $user->id)->exists();
            if ($duel && !$alreadySeated) { $this->assertCanPair($table, $user, $ip); $this->assertDailyCap($user, (string) $table->stake); }
            $joined = $this->tables->acceptInvite($user, $inviteId);
            if ($duel) {
                FantasyDuelSeat::firstOrCreate(['table_game_id' => $table->id, 'user_id' => $user->id], ['entry_ip' => $ip]);
                if (!$alreadySeated) $this->flagRepeatOpponents($table, $user);
            }
            return $joined;
        }, 3);
    }

    public function invite(User $sender, string $tableId, string $username): \App\Models\TableGameInvite
    {
        $table = TableGame::findOrFail($tableId);
        $duel = FantasyDuel::where('table_game_id', $tableId)->first();
        if ($duel) {
            $invitee = User::where('username', trim($username))->first();
            if (!$invitee) throw new InvalidArgumentException('No player was found with that username.');
            $this->assertCanPair($table, $invitee, null);
            $this->assertDailyCap($invitee, (string) $table->stake);
        }
        return $this->tables->invite($sender, $tableId, $username);
    }

    public function ready(User $user, string $tableId): array
    {
        return DB::transaction(function () use ($user, $tableId) {
            $table = TableGame::whereKey($tableId)->lockForUpdate()->firstOrFail();
            $duel = FantasyDuel::where('table_game_id', $tableId)->lockForUpdate()->firstOrFail();
            if (!TableGamePlayer::where('table_game_id', $tableId)->where('user_id', $user->id)->where('status', 'seated')->exists()) throw new InvalidArgumentException('Only seated players can ready up.');
            if ($duel->phase !== 'ready') throw new InvalidArgumentException('The ready check is not open.');
            $seat = FantasyDuelSeat::where('table_game_id', $tableId)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $seat->ready_at ??= now();
            $seat->save();
            $this->audit($tableId, $user->id, 'player_ready');
            $required = TableGamePlayer::where('table_game_id', $tableId)->where('status', 'seated')->count();
            if (FantasyDuelSeat::where('table_game_id', $tableId)->whereNotNull('ready_at')->count() >= $required) $this->beginBuild($duel);
            return $this->snapshot($user, $tableId);
        }, 3);
    }

    public function submitSquad(User $user, string $tableId, array $ids, int $captainId): array
    {
        return DB::transaction(function () use ($user, $tableId, $ids, $captainId) {
            $duel = FantasyDuel::where('table_game_id', $tableId)->lockForUpdate()->firstOrFail();
            if ($duel->phase !== 'building' || !$duel->build_deadline_at || now()->greaterThan($duel->build_deadline_at)) throw new InvalidArgumentException('The team-build window is closed.');
            if (!TableGamePlayer::where('table_game_id', $tableId)->where('user_id', $user->id)->where('status', 'seated')->exists()) throw new InvalidArgumentException('Only seated players can build a duel squad.');
            $players = $this->validateSquad($ids, $captainId, (float) $duel->budget_tier);
            $row = FantasyDuelSquad::updateOrCreate(
                ['table_game_id' => $tableId, 'user_id' => $user->id],
                ['player_ids' => $players->keys()->map(fn ($id) => (int) $id)->all(), 'captain_id' => $captainId, 'auto_submitted' => false, 'submitted_at' => now()]
            );
            $this->audit($tableId, $user->id, 'squad_submitted', ['player_count' => $players->count(), 'tier' => $duel->budget_tier]);
            return $this->snapshot($user, $tableId);
        }, 3);
    }

    public function snapshot(User $user, string $tableId, int $eventCursor = 0): array
    {
        $this->advance($tableId);
        $table = TableGame::with('players.user')->findOrFail($tableId);
        $duel = FantasyDuel::where('table_game_id', $tableId)->firstOrFail();
        $isSeat = $table->players->contains(fn ($p) => (int) $p->user_id === (int) $user->id && $p->status === 'seated');
        $me = FantasyDuelSquad::where('table_game_id', $tableId)->where('user_id', $user->id)->first();
        $liveDuration = max(60, (int) (gs('ff_duel_live_minutes') ?: 10) * 60);
        $elapsedSeconds = $duel->live_started_at ? max(0, now()->timestamp - $duel->live_started_at->timestamp) : 0;
        $elapsedRatio = $duel->phase === 'live' && $duel->live_started_at ? min(1, $elapsedSeconds / $liveDuration) : 0;
        $minute = $duel->phase === 'live' ? (int) floor($elapsedRatio * 90) : ($duel->phase === 'finished' ? 90 : 0);
        $events = [];
        $matches = [];
        $partialPlayerStats = [];
        $liveRules = $this->scoring->rules();
        foreach ((array) $duel->slate as $index => $match) {
            $nameMap = $match['player_names'] ?? [];
            $liveEvents = array_values(array_filter($match['result']['events'] ?? [], fn ($event) => (int) ($event['minute'] ?? 0) <= $minute));
            foreach ($liveEvents as $event) $events[] = ['match' => $index + 1, 'minute' => $event['minute'], 'type' => $event['type'], 'player' => $nameMap[(string) ($event['player_id'] ?? '')] ?? 'Player', 'side' => $event['side'] ?? 'home'];
            $homeScore = $duel->phase === 'finished' ? $match['result']['home_score'] : $this->partialScore($liveEvents, 'home');
            $awayScore = $duel->phase === 'finished' ? $match['result']['away_score'] : $this->partialScore($liveEvents, 'away');
            $matches[] = ['home' => $match['home']['name'], 'away' => $match['away']['name'], 'home_score' => $homeScore, 'away_score' => $awayScore];
            if ($duel->phase === 'live') {
                foreach ($match['result']['player_stats'] as $final) {
                    if (empty($final['started']) || $minute <= 0) continue;
                    $id = (int) $final['id']; $side = $match['player_sides'][(string) $id] ?? 'home';
                    $stat = ['minutes' => min((int) $final['minutes'], $minute), 'goals' => 0, 'assists' => 0, 'yellow_cards' => 0, 'red_cards' => 0, 'own_goals' => 0,
                        'penalties_saved' => 0, 'penalties_missed' => 0, 'saves' => 0, 'goals_conceded' => $side === 'home' ? $awayScore : $homeScore,
                        'clean_sheet' => ($side === 'home' ? $awayScore : $homeScore) === 0, 'bonus' => 0];
                    foreach ($liveEvents as $event) {
                        if ((int) ($event['player_id'] ?? 0) === $id) {
                            if (in_array($event['type'], ['goal', 'penalty_scored'], true)) $stat['goals']++;
                            if ($event['type'] === 'own_goal') $stat['own_goals']++;
                            if ($event['type'] === 'yellow') $stat['yellow_cards']++;
                            if ($event['type'] === 'red') $stat['red_cards']++;
                            if ($event['type'] === 'penalty_missed') $stat['penalties_missed']++;
                        }
                        if ((int) ($event['secondary_player_id'] ?? 0) === $id && ($event['type'] ?? '') === 'goal') $stat['assists']++;
                    }
                    $partialPlayerStats[$id] = ['position' => $final['position'], 'stats' => $stat];
                }
            }
        }
        usort($events, fn ($a, $b) => [$a['minute'], $a['match']] <=> [$b['minute'], $b['match']]);
        $eventCount = count($events);
        if ($eventCursor > 0) $events = array_slice($events, min($eventCursor, $eventCount));
        $squads = FantasyDuelSquad::where('table_game_id', $tableId)->with('user:id,username')->orderByDesc('points')->get();
        $playerModels = $duel->phase === 'live' ? FantasyPlayer::whereIn('id', $squads->flatMap(fn ($s) => $s->player_ids ?? [])->unique())->get()->keyBy('id') : collect();
        $standings = $squads->map(function ($s) use ($partialPlayerStats, $playerModels, $duel, $user, $liveRules) {
            $points = (int) $s->points;
            if ($duel->phase === 'live') {
                $points = 0;
                foreach ($s->player_ids as $id) {
                    $stats = $partialPlayerStats[(int) $id] ?? null; $player = $playerModels[(int) $id] ?? null;
                    if (!$stats || !$player) continue;
                    $playerPoints = $this->scoring->calculate($player->position, $stats['stats'], $liveRules);
                    $points += $playerPoints + ((int) $id === (int) $s->captain_id ? $playerPoints : 0);
                }
            }
            return ['username' => $s->user?->username ?? 'Player', 'points' => $points, 'is_you' => (int) $s->user_id === (int) $user->id, 'auto' => (bool) $s->auto_submitted];
        })->sortByDesc('points')->values()->all();
        $readyUsers = FantasyDuelSeat::where('table_game_id', $tableId)->whereNotNull('ready_at')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $payload = [
            'table_id' => $tableId, 'status' => $table->status, 'phase' => $duel->phase, 'tier' => (float) $duel->budget_tier,
            'stake' => (string) $table->stake, 'players' => $table->players->map(fn ($p) => ['username' => $p->user?->username ?? 'Player', 'seat' => (int) $p->seat, 'ready' => in_array((int) $p->user_id, $readyUsers, true), 'is_you' => (int) $p->user_id === (int) $user->id])->values()->all(),
            'is_seated' => $isSeat, 'commitment' => $duel->seed_commitment, 'deadline' => $this->deadline($duel)?->toIso8601String(),
            'minute' => $minute, 'matches' => $matches, 'events' => $events, 'event_cursor' => $eventCount, 'standings' => $standings,
            'my_squad' => $me ? ['player_ids' => $me->player_ids, 'captain_id' => $me->captain_id, 'auto_submitted' => $me->auto_submitted] : null,
            'can_build' => $isSeat && $duel->phase === 'building' && $duel->build_deadline_at?->isFuture(),
            'ready' => $duel->phase === 'ready' && $isSeat,
            'seed' => $duel->phase === 'finished' ? Crypt::decryptString($duel->sealed_seed) : null,
            'result' => $duel->phase === 'finished' ? $duel->result : null,
        ];
        if ($payload['can_build']) {
            $payload['player_catalog'] = FantasyPlayer::active()->with('club')->where('status', 'fit')->orderBy('position')->orderByDesc('rating')->get()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'position' => $p->position, 'club_id' => $p->club_id, 'club' => $p->club?->name, 'price' => (float) $p->price, 'rating' => (float) $p->rating])->values()->all();
        }
        return $payload;
    }

    public function verify(string $tableId): array
    {
        $duel = FantasyDuel::where('table_game_id', $tableId)->firstOrFail();
        if ($duel->phase !== 'finished' || !$duel->seed_revealed_at) throw new InvalidArgumentException('The slate can be verified after the duel ends.');
        $seed = Crypt::decryptString($duel->sealed_seed);
        $checks = [];
        foreach ((array) $duel->slate as $index => $match) {
            $home = FantasyClub::findOrFail($match['home']['id']);
            $away = FantasyClub::findOrFail($match['away']['id']);
            $result = $this->simulator->simulate(new DeterministicRandom($seed . ':fixture:' . $index), $home->toArray(), $home->players()->active()->where('status', 'fit')->get()->map->toArray(), $away->toArray(), $away->players()->active()->where('status', 'fit')->get()->map->toArray());
            $checks[] = ['match' => $index + 1, 'valid' => hash_equals(hash('sha256', json_encode($result)), hash('sha256', json_encode($match['result'])))];
        }
        return ['commitment' => $duel->seed_commitment, 'seed' => $seed, 'commitment_valid' => hash_equals($duel->seed_commitment, hash('sha256', $seed)), 'matches' => $checks, 'valid' => hash_equals($duel->seed_commitment, hash('sha256', $seed)) && collect($checks)->every(fn ($c) => $c['valid'])];
    }

    public function sweep(): int
    {
        if (!Schema::hasTable('ff_duels')) return 0;
        $count = 0;
        FantasyDuel::whereIn('phase', ['lobby', 'ready', 'building', 'live'])->orderBy('id')->limit(200)->pluck('table_game_id')->each(function ($id) use (&$count) {
            $before = FantasyDuel::where('table_game_id', $id)->value('phase');
            $this->advance((string) $id);
            if ($before !== FantasyDuel::where('table_game_id', $id)->value('phase')) $count++;
        });
        return $count;
    }

    private function advance(string $tableId): void
    {
        DB::transaction(function () use ($tableId) {
            $duel = FantasyDuel::where('table_game_id', $tableId)->lockForUpdate()->first();
            if (!$duel || in_array($duel->phase, ['finished', 'cancelled'], true)) return;
            $table = TableGame::whereKey($tableId)->lockForUpdate()->first();
            if (!$table) return;
            if (in_array($table->status, ['force_closed', 'finished', 'cancelled', 'expired'], true)) {
                $duel->phase = 'cancelled'; $duel->save(); $this->audit($tableId, null, 'duel_cancelled', ['reason' => 'table_closed']); return;
            }
            if ($duel->phase === 'lobby' && $table->status === 'active') {
                $duel->phase = 'ready'; $duel->ready_started_at = now(); $duel->ready_deadline_at = now()->addSeconds(max(15, (int) (gs('ff_duel_ready_seconds') ?: 60))); $duel->save();
            }
            if ($duel->phase === 'lobby' && $table->join_deadline_at && now()->greaterThan($table->join_deadline_at)) {
                $this->tables->forceClose($tableId); $duel->phase = 'cancelled'; $duel->save(); $this->audit($tableId, null, 'duel_cancelled', ['reason' => 'lobby_timeout']); return;
            }
            if ($duel->phase === 'ready' && $duel->ready_deadline_at && now()->greaterThan($duel->ready_deadline_at)) {
                $this->tables->forceClose($tableId); $duel->phase = 'cancelled'; $duel->save(); $this->audit($tableId, null, 'duel_cancelled', ['reason' => 'ready_timeout']); return;
            }
            if ($duel->phase === 'building' && $duel->build_deadline_at && now()->greaterThanOrEqualTo($duel->build_deadline_at)) {
                foreach (TableGamePlayer::where('table_game_id', $tableId)->where('status', 'seated')->get() as $seat) {
                    if (!FantasyDuelSquad::where('table_game_id', $tableId)->where('user_id', $seat->user_id)->exists()) $this->saveAutoSquad($duel, (int) $seat->user_id);
                }
                $minutes = max(1, min(60, (int) (gs('ff_duel_live_minutes') ?: 10)));
                $duel->phase = 'live'; $duel->live_started_at = now(); $duel->live_ends_at = now()->addMinutes($minutes); $duel->save();
                $table->turn_deadline_at = null; $table->save();
                $this->audit($tableId, null, 'duel_live_started', ['auto_squads' => FantasyDuelSquad::where('table_game_id', $tableId)->where('auto_submitted', true)->count()]);
            }
            if ($duel->phase === 'live' && $duel->live_ends_at && now()->greaterThanOrEqualTo($duel->live_ends_at)) $this->finish($table, $duel);
        }, 3);
    }

    private function beginBuild(FantasyDuel $duel): void
    {
        $minutes = max(1, min(15, (int) (gs('ff_duel_build_minutes') ?: 3)));
        $duel->phase = 'building'; $duel->build_started_at = now(); $duel->build_deadline_at = now()->addMinutes($minutes); $duel->save();
        $this->audit($duel->table_game_id, null, 'duel_build_started', ['minutes' => $minutes]);
    }

    private function validateSquad(array $ids, int $captainId, float $budget): \Illuminate\Support\Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) !== 11 || min($ids) < 1 || !in_array($captainId, $ids, true)) throw new InvalidArgumentException('Choose 11 different players and select one of them as captain.');
        $players = FantasyPlayer::active()->where('status', 'fit')->with('club')->whereIn('id', $ids)->get()->keyBy('id');
        if ($players->count() !== 11) throw new InvalidArgumentException('One or more selected players are no longer available.');
        $counts = $players->countBy('position');
        if ($counts->get('GK', 0) !== 1 || $counts->get('DEF', 0) < 3 || $counts->get('DEF', 0) > 5 || $counts->get('MID', 0) < 2 || $counts->get('MID', 0) > 5 || $counts->get('FWD', 0) < 1 || $counts->get('FWD', 0) > 3) throw new InvalidArgumentException('Use a legal formation: 1 goalkeeper, 3–5 defenders, 2–5 midfielders and 1–3 forwards.');
        if ((float) $players->sum('price') > $budget + 0.00001) throw new InvalidArgumentException('Your starting eleven is over the tier budget.');
        if ($players->countBy('club_id')->max() > 3) throw new InvalidArgumentException('Choose no more than three players from one club.');
        return $players;
    }

    private function saveAutoSquad(FantasyDuel $duel, int $userId): void
    {
        $selection = $this->buildAutomaticSquad((float) $duel->budget_tier);
        FantasyDuelSquad::create(['table_game_id' => $duel->table_game_id, 'user_id' => $userId, 'player_ids' => $selection['player_ids'], 'captain_id' => $selection['captain_id'], 'auto_submitted' => true, 'submitted_at' => $duel->build_deadline_at ?? now()]);
    }

    /** Deterministic best-value legal 4-4-2 fallback used when a player misses the builder deadline. */
    public function buildAutomaticSquad(float $tier): array
    {
        $players = FantasyPlayer::active()->where('status', 'fit')->get()->groupBy('position');
        $picked = [];
        $clubCounts = [];
        $budget = $tier;
        $positions = ['GK' => 1, 'DEF' => 4, 'MID' => 4, 'FWD' => 2];
        foreach ($positions as $position => $needed) {
            $candidates = ($players[$position] ?? collect())->sortBy('price')->values();
            foreach ($candidates as $player) {
                if (count(array_filter($picked, fn ($p) => $p->position === $position)) >= $needed) break;
                if (($clubCounts[$player->club_id] ?? 0) >= 3 || (float) $player->price > $budget) continue;
                $picked[] = $player; $clubCounts[$player->club_id] = ($clubCounts[$player->club_id] ?? 0) + 1; $budget -= (float) $player->price;
            }
            if (count(array_filter($picked, fn ($p) => $p->position === $position)) < $needed) throw new InvalidArgumentException('The available virtual player pool cannot fill a legal duel squad.');
        }
        // Upgrade affordable slots in value order; the initial XI is valid and every replacement preserves its shape.
        $upgrades = [];
        foreach ($picked as $current) foreach (($players[$current->position] ?? collect()) as $candidate) {
            if ($candidate->id !== $current->id && (float) $candidate->rating > (float) $current->rating) $upgrades[] = [$current, $candidate, ((float) $candidate->rating - (float) $current->rating) / max(.1, (float) $candidate->price - (float) $current->price)];
        }
        usort($upgrades, fn ($a, $b) => $b[2] <=> $a[2]);
        foreach ($upgrades as [$current, $candidate]) {
            if (!in_array($current, $picked, true) || in_array($candidate, $picked, true)) continue;
            $delta = (float) $candidate->price - (float) $current->price;
            if ($delta <= $budget && ($clubCounts[$candidate->club_id] ?? 0) < 3) {
                $picked[array_search($current, $picked, true)] = $candidate; $budget -= $delta;
                $clubCounts[$current->club_id]--; $clubCounts[$candidate->club_id] = ($clubCounts[$candidate->club_id] ?? 0) + 1;
            }
        }
        $picked = collect($picked);
        $captain = $picked->sortByDesc('rating')->first();
        return ['player_ids' => $picked->pluck('id')->map(fn ($id) => (int) $id)->all(), 'captain_id' => (int) $captain->id, 'cost' => round($picked->sum('price'), 1), 'budget_remaining' => round($budget, 1)];
    }

    private function finish(TableGame $table, FantasyDuel $duel): void
    {
        $seed = Crypt::decryptString($duel->sealed_seed);
        $rules = $this->scoring->rules();
        $squads = FantasyDuelSquad::where('table_game_id', $table->id)->orderBy('submitted_at')->get();
        $statsByPlayer = [];
        foreach ((array) $duel->slate as $match) foreach ($match['result']['player_stats'] as $stats) $statsByPlayer[(int) $stats['id']] = $stats;
        foreach ($squads as $squad) {
            $players = FantasyPlayer::whereIn('id', $squad->player_ids)->get()->keyBy('id');
            $sum = 0; $captainPoints = 0; $goals = 0;
            foreach ($squad->player_ids as $id) {
                $player = $players[(int) $id] ?? null; $stats = $statsByPlayer[(int) $id] ?? [];
                if (!$player) continue;
                $points = $this->scoring->calculate($player->position, $stats, $rules);
                $sum += $points; $goals += (int) ($stats['goals'] ?? 0);
                if ((int) $id === (int) $squad->captain_id) $captainPoints = $points;
            }
            $squad->points = $sum + $captainPoints; $squad->goals = $goals; $squad->captain_points = $captainPoints; $squad->save();
        }
        $ordered = $squads->fresh()->sort(function ($a, $b) {
            foreach (['points', 'goals', 'captain_points'] as $key) if ((int) $a->$key !== (int) $b->$key) return (int) $b->$key <=> (int) $a->$key;
            return $a->submitted_at <=> $b->submitted_at;
        })->values();
        $first = $ordered->first();
        $winners = $ordered->filter(fn ($row) => $first && $row->points === $first->points && $row->goals === $first->goals && $row->captain_points === $first->captain_points && $row->submitted_at->equalTo($first->submitted_at));
        foreach ($ordered as $index => $row) { $row->place = $index + 1; $row->save(); }
        $this->settle($table, $winners->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $duel->phase = 'finished'; $duel->seed_revealed_at = now();
        $duel->result = ['winners' => $winners->pluck('user_id')->map(fn ($id) => (int) $id)->all(), 'pot' => bcmul((string) $table->stake, (string) $table->player_limit, 0), 'commitment' => $duel->seed_commitment, 'seed' => $seed];
        $duel->save();
        $winnerSeats = TableGamePlayer::where('table_game_id', $table->id)->whereIn('user_id', $duel->result['winners'])->orderBy('seat')->pluck('seat')->map(fn ($seat) => (int) $seat)->all();
        $table->status = 'finished';
        $table->result = count($winnerSeats) > 1 ? 'winners:' . implode(',', $winnerSeats) : 'winner:' . ($winnerSeats[0] ?? 0);
        $table->finished_at = now(); $table->turn_deadline_at = null; $table->save();
        $this->audit($table->id, null, 'duel_settled', ['winners' => $duel->result['winners'], 'pot' => $duel->result['pot'], 'commitment' => $duel->seed_commitment]);
    }

    private function settle(TableGame $table, array $winnerIds): void
    {
        $players = TableGamePlayer::where('table_game_id', $table->id)->where('status', 'seated')->lockForUpdate()->orderBy('user_id')->get();
        $count = $players->count();
        if (!$count || !$winnerIds) throw new InvalidArgumentException('Cannot settle a duel without seated players and a winner.');
        $pot = bcmul((string) $table->stake, (string) $count, 0);
        $feeRate = (string) data_get($table->settings, 'commission_percent', 5);
        $fee = bcdiv(bcmul($pot, $feeRate, 4), '100', 0); // whole-coin fee, rounded down
        $net = bcsub($pot, $fee, 0);
        $share = bcdiv($net, (string) count($winnerIds), 0);
        $payouts = array_fill_keys($players->pluck('user_id')->map(fn ($id) => (int) $id)->all(), '0');
        $paid = '0';
        foreach ($winnerIds as $index => $userId) {
            $payouts[$userId] = $index === count($winnerIds) - 1 ? bcsub($net, $paid, 0) : $share;
            $paid = bcadd($paid, $payouts[$userId], 0);
        }
        $sources = [];
        $sinks = [['type' => 'platform', 'id' => CoinLedger::PLATFORM_OWNER_ID, 'left' => $fee]];
        foreach ($players as $player) {
            $userId = (int) $player->user_id;
            $ownRelease = min((int) $table->stake, (int) $payouts[$userId]);
            if ($ownRelease > 0) app(CoinLedger::class)->release('user', $userId, (string) $ownRelease, 'fantasy_duel_stake_release', $table->id . ':duel:release:' . $userId, 'TableGame', null);
            $source = (int) $table->stake - $ownRelease;
            if ($source > 0) $sources[] = ['user' => $userId, 'left' => (string) $source];
            $extra = (int) $payouts[$userId] - $ownRelease;
            if ($extra > 0) $sinks[] = ['type' => 'user', 'id' => $userId, 'left' => (string) $extra];
            $player->settlement_key = $table->id . ':duel:settle:' . $userId;
            $player->settlement_amount = (string) $payouts[$userId];
            $player->settlement_type = in_array($userId, $winnerIds, true) ? 'payout' : 'loss';
            $player->save();
        }
        $sinkIndex = 0;
        foreach ($sources as $source) {
            while (bccomp($source['left'], '0', 0) > 0) {
                while ($sinkIndex < count($sinks) && bccomp($sinks[$sinkIndex]['left'], '0', 0) <= 0) $sinkIndex++;
                if (!isset($sinks[$sinkIndex])) throw new \LogicException('Duel settlement amounts do not reconcile.');
                $amount = min((int) $source['left'], (int) $sinks[$sinkIndex]['left']);
                app(CoinLedger::class)->settleHeldToOwner('user', $source['user'], $sinks[$sinkIndex]['type'], $sinks[$sinkIndex]['id'], (string) $amount, $sinks[$sinkIndex]['type'] === 'platform' ? 'fantasy_duel_commission' : 'fantasy_duel_payout', $table->id . ':duel:transfer:' . $source['user'] . ':' . $sinks[$sinkIndex]['type'] . ':' . $sinks[$sinkIndex]['id'], 'TableGame', null);
                $source['left'] = bcsub($source['left'], (string) $amount, 0); $sinks[$sinkIndex]['left'] = bcsub($sinks[$sinkIndex]['left'], (string) $amount, 0);
            }
        }
        if ((int) $fee > 0) TableGameCommission::updateOrCreate(['table_game_id' => $table->id], ['game_type' => 'fantasy_duel', 'amount' => $fee, 'rate_percent' => $feeRate, 'is_coin_mode' => true, 'status' => 'retained']);
    }

    private function createSlate(string $seed): array
    {
        $clubs = FantasyClub::where('is_active', true)->orderBy('id')->get();
        if ($clubs->count() < 8) throw new InvalidArgumentException('The virtual league needs at least eight active clubs before a Live Duel can be created.');
        $rng = new DeterministicRandom($seed . ':pairings'); $ids = $clubs->pluck('id')->all();
        for ($i = count($ids) - 1; $i > 0; $i--) { $j = $rng->nextInt(0, $i); [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; }
        $fixtureCount = min(max(4, min(6, (int) (gs('ff_duel_fixture_count') ?: 4))), intdiv($clubs->count(), 2));
        $slate = [];
        for ($index = 0; $index < $fixtureCount; $index++) {
            $home = $clubs->firstWhere('id', $ids[$index * 2]); $away = $clubs->firstWhere('id', $ids[$index * 2 + 1]);
            $homeRoster = $home->players()->active()->where('status', 'fit')->get(); $awayRoster = $away->players()->active()->where('status', 'fit')->get();
            $result = $this->simulator->simulate(new DeterministicRandom($seed . ':fixture:' . $index), $home->toArray(), $homeRoster->map->toArray(), $away->toArray(), $awayRoster->map->toArray());
            $playerNames = FantasyPlayer::whereIn('id', array_column($result['player_stats'], 'id'))->pluck('name', 'id')->all();
            $sides = [];
            foreach ($homeRoster as $player) $sides[(string) $player->id] = 'home';
            foreach ($awayRoster as $player) $sides[(string) $player->id] = 'away';
            $slate[] = ['home' => ['id' => $home->id, 'name' => $home->name], 'away' => ['id' => $away->id, 'name' => $away->name], 'player_names' => $playerNames, 'player_sides' => $sides, 'result' => $result];
        }
        return $slate;
    }

    private function assertCanPair(TableGame $table, User $user, ?string $ip): void
    {
        if (TableGamePlayer::where('table_game_id', $table->id)->where('user_id', $user->id)->exists()) return;
        $seated = TableGamePlayer::where('table_game_id', $table->id)->where('status', 'seated')->pluck('user_id');
        foreach ($seated as $otherId) {
            if ((int) $otherId === (int) $user->id) throw new InvalidArgumentException('A player cannot take two seats at the same duel.');
            if (Schema::hasTable('user_blocks') && UserBlock::where(fn ($q) => $q->where('blocker_id', $user->id)->where('blocked_id', $otherId)->orWhere(fn ($r) => $r->where('blocker_id', $otherId)->where('blocked_id', $user->id)))->exists()) throw new InvalidArgumentException('This player cannot join a duel with an account they have blocked or that has blocked them.');
        }
        if ($ip && FantasyDuelSeat::where('table_game_id', $table->id)->where('entry_ip', $ip)->exists()) throw new InvalidArgumentException('Only one account from the same network can join this duel.');
        if (Schema::hasColumn('users', 'register_ip') && $seated->isNotEmpty() && User::whereIn('id', $seated)->where('register_ip', $user->register_ip)->whereNotNull('register_ip')->exists()) throw new InvalidArgumentException('Linked accounts cannot enter the same duel.');
    }

    private function assertDailyCap(User $user, string $stake): void
    {
        $maximum = (string) (gs('ff_max_entry_stake') ?: '10000');
        $daily = (string) (gs('ff_daily_stake_cap') ?: '50000');
        if (bccomp($stake, $maximum, 8) > 0) throw new InvalidArgumentException('The stake exceeds the maximum allowed per entry.');
        $used = (string) DB::table('ff_duel_seats')->join('table_games', 'table_games.id', '=', 'ff_duel_seats.table_game_id')->where('ff_duel_seats.user_id', $user->id)->where('ff_duel_seats.created_at', '>=', now()->startOfDay())->sum('table_games.stake');
        if (bccomp(bcadd($used, $stake, 8), $daily, 8) > 0) throw new InvalidArgumentException('You have reached your daily Live Duel staking limit.');
    }

    private function flagRepeatOpponents(TableGame $table, User $user): void
    {
        $others = TableGamePlayer::where('table_game_id', $table->id)->where('user_id', '!=', $user->id)->pluck('user_id');
        foreach ($others as $other) {
            $past = DB::table('ff_duel_seats as a')->join('ff_duel_seats as b', 'a.table_game_id', '=', 'b.table_game_id')->join('table_games as t', 't.id', '=', 'a.table_game_id')->where('a.user_id', $user->id)->where('b.user_id', $other)->where('a.table_game_id', '!=', $table->id)->where('t.status', 'finished')->exists();
            if ($past) $this->audit($table->id, $user->id, 'repeat_opponent_flag', ['opponent_user_id' => (int) $other]);
        }
    }

    private function deadline(FantasyDuel $duel): ?\Carbon\CarbonInterface
    {
        return match ($duel->phase) { 'lobby' => TableGame::whereKey($duel->table_game_id)->first()?->join_deadline_at, 'ready' => $duel->ready_deadline_at, 'building' => $duel->build_deadline_at, 'live' => $duel->live_ends_at, default => null };
    }

    private function partialScore(array $events, string $side): int
    {
        return count(array_filter($events, fn ($e) => ($e['side'] ?? null) === $side && in_array($e['type'] ?? '', ['goal', 'penalty_scored', 'own_goal'], true)));
    }

    private function audit(string $tableId, ?int $userId, string $event, array $payload = []): void
    {
        DB::table('ff_duel_audits')->insert(['table_game_id' => $tableId, 'user_id' => $userId, 'event' => $event, 'payload' => json_encode($payload), 'created_at' => now()]);
    }
}
