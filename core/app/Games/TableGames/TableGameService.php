<?php

namespace App\Games\TableGames;

use App\Models\TableGame;
use App\Models\TableGameCommission;
use App\Models\TableGameInvite;
use App\Models\TableGameMove;
use App\Models\TableGamePlayer;
use App\Models\TableGameSetting;
use App\Models\TableGameUserLimit;
use App\Models\Transaction;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TableGameService
{
    private array $schemaColumnCache = [];

    public function __construct(private readonly TableGameRules $rules = new TableGameRules()) {}

    public function catalog(): array
    {
        return collect(TableGameRules::GAMES)->map(function ($definition, $key) {
            $setting = $this->setting($key);
            return [
                'key' => $key,
                'name' => $definition['name'],
                'min_players' => $definition['min'],
                'max_players' => $definition['max'],
                'enabled' => (bool) $setting->enabled,
                'card_image' => $setting->card_image ?? null,
                'available' => in_array($key, TableGameRules::AVAILABLE, true),
                'commission_percent' => (float) $setting->commission_percent,
                'min_stake' => (string) $setting->min_stake,
                'max_stake' => (string) $setting->max_stake,
                'draw_rule' => $this->drawRule($key),
            ];
        })->values()->all();
    }

    public function create(User $user, string $gameType, int $playerLimit, string $stake, string $idempotencyKey): TableGame
    {
        return DB::transaction(function () use ($user, $gameType, $playerLimit, $stake, $idempotencyKey) {
            if (!preg_match('/^[A-Za-z0-9_-]{12,64}$/', $idempotencyKey)) throw new InvalidArgumentException('A valid request key is required.');
            $existing = TableGame::where('create_idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->creator_id !== (int) $user->id) throw new InvalidArgumentException('That request key is already in use.');
                return $existing->fresh(['players.user']);
            }
            $setting = $this->setting($gameType);
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = TableGame::where('create_idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                if ((int) $existing->creator_id !== (int) $user->id) throw new InvalidArgumentException('That request key is already in use.');
                return $existing->fresh(['players.user']);
            }
            $this->validateCreate($lockedUser, $gameType, $playerLimit, $stake, $setting);
            $creatorRate = bccomp($stake, '0', 8) > 0 ? $this->currentCurrencyRate(Currency::findOrFail($lockedUser->currency_id)) : null;
            $tableData = [
                'game_type' => $gameType,
                'creator_id' => $lockedUser->id,
                'create_idempotency_key' => $idempotencyKey,
                'player_limit' => $playerLimit,
                'stake' => $stake,
                'status' => 'waiting',
                'join_deadline_at' => now()->addHours(24),
                'settings' => ['commission_percent' => (float) $setting->commission_percent, 'turn_seconds' => (int) $setting->turn_seconds],
            ];
            if ($this->hasColumn('table_games', 'currency_id')) $tableData['currency_id'] = $lockedUser->currency_id;
            $table = TableGame::create($tableData);
            $this->debit($lockedUser, $table, 'creator_stake');
            $playerData = [
                'table_game_id' => $table->id,
                'user_id' => $lockedUser->id,
                'seat' => 0,
                'stake' => $stake,
                'wallet_stake' => $stake,
                'wallet_rate_to_usd' => $creatorRate,
                'table_rate_to_usd' => $creatorRate,
                'status' => 'seated',
                'stake_debit_key' => $table->id . ':stake:' . $lockedUser->id,
                'last_seen_at' => now(),
            ];
            if ($this->hasColumn('table_game_players', 'currency_id')) $playerData['currency_id'] = $lockedUser->currency_id;
            TableGamePlayer::create($playerData);
            return $table->fresh(['players.user']);
        }, 3);
    }

    public function join(User $user, string $id): TableGame
    {
        return DB::transaction(function () use ($user, $id) {
            $table = TableGame::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->expireIfNeeded($table);
            if ($table->status !== 'waiting') {
                if ($table->players()->where('user_id', $user->id)->exists()) return $table->fresh(['players.user']);
                throw new InvalidArgumentException('This table is no longer accepting players.');
            }
            $setting = $this->setting($table->game_type);
            if (!$setting->enabled) throw new InvalidArgumentException('Joining this game is currently disabled.');
            if (TableGamePlayer::where('table_game_id', $table->id)->where('user_id', $user->id)->exists()) {
                return $table->fresh(['players.user']);
            }
            if (TableGamePlayer::where('table_game_id', $table->id)->where('status', 'seated')->count() >= $table->player_limit) {
                throw new InvalidArgumentException('This table is full.');
            }
            $userLimit = TableGameUserLimit::where('user_id', $user->id)->first();
            if ($userLimit?->banned) throw new InvalidArgumentException('Your access to live tables is restricted.');
            $maxTables = $userLimit?->max_open_tables ?? $setting->max_open_tables_per_user;
            $openTables = TableGame::whereIn('status', ['waiting', 'active'])->whereHas('players', fn ($players) => $players->where('user_id', $user->id)->where('status', 'seated'))->distinct('table_games.id')->count('table_games.id');
            if ($openTables >= (int) $maxTables) throw new InvalidArgumentException('You have reached your open table limit.');
            $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $occupiedSeats = TableGamePlayer::where('table_game_id', $table->id)->where('status', 'seated')->pluck('seat')->map(fn ($value) => (int) $value)->all();
            $seat = 0;
            while (in_array($seat, $occupiedSeats, true)) $seat++;
            $stakeQuote = $this->walletStakeQuoteFor($lockedUser, $table);
            $this->validateParticipantWalletStake($lockedUser, $stakeQuote['wallet_stake'], $table);
            $this->debit($lockedUser, $table, 'player_stake', $stakeQuote['wallet_stake']);
            $playerData = [
                'table_game_id' => $table->id,
                'user_id' => $lockedUser->id,
                'seat' => $seat,
                'stake' => $table->stake,
                'wallet_stake' => $stakeQuote['wallet_stake'],
                'wallet_rate_to_usd' => $stakeQuote['wallet_rate_to_usd'],
                'table_rate_to_usd' => $stakeQuote['table_rate_to_usd'],
                'status' => 'seated',
                'stake_debit_key' => $table->id . ':stake:' . $lockedUser->id,
                'last_seen_at' => now(),
            ];
            if ($this->hasColumn('table_game_players', 'currency_id')) $playerData['currency_id'] = $lockedUser->currency_id;
            TableGamePlayer::create($playerData);
            $table->refresh();
            if ($table->players()->where('status', 'seated')->count() >= $table->player_limit) $this->startLocked($table);
            return $table->fresh(['players.user']);
        }, 3);
    }

    public function invite(User $sender, string $tableId, string $username): TableGameInvite
    {
        return DB::transaction(function () use ($sender, $tableId, $username) {
            $table = TableGame::whereKey($tableId)->lockForUpdate()->firstOrFail();
            $this->expireIfNeeded($table);
            if ($table->status !== 'waiting') {
                $message = match ($table->result) {
                    'expired_refunded' => 'This table finished after its 24-hour waiting period and all stakes were refunded. Create a new table to invite players.',
                    'cancelled_refunded' => 'The table creator cancelled this table and all stakes were refunded.',
                    default => $table->status === 'active'
                        ? 'This game has already started. New players cannot join a game in progress.'
                        : 'This table is no longer waiting for players. Create a new table to send an invitation.',
                };
                throw new InvalidArgumentException($message);
            }
            if ((int) $table->creator_id !== (int) $sender->id) throw new InvalidArgumentException('Only the table creator can invite players.');
            if ($table->players()->where('status', 'seated')->count() >= $table->player_limit) throw new InvalidArgumentException('This table is already full.');
            $invitee = User::where('username', trim($username))->first();
            if (!$invitee) throw new InvalidArgumentException('No player was found with that username.');
            $inviteeQuote = $this->walletStakeQuoteFor($invitee, $table);
            $this->validateParticipantWalletStake($invitee, $inviteeQuote['wallet_stake'], $table);
            if ((int) $invitee->id === (int) $sender->id) throw new InvalidArgumentException('You are already seated at this table.');
            if ($table->players()->where('user_id', $invitee->id)->exists()) throw new InvalidArgumentException('That player already has a seat at this table.');

            $invite = TableGameInvite::where('table_game_id', $table->id)->where('invitee_id', $invitee->id)->lockForUpdate()->first();
            if ($invite && $invite->status === 'pending' && $invite->expires_at?->isFuture()) return $invite;
            if ($invite) {
                $invite->inviter_id = $sender->id;
                $invite->status = 'pending';
                $invite->expires_at = $table->join_deadline_at ?? now()->addHours(24);
                $invite->accepted_at = null;
                $invite->save();
                return $invite->fresh(['table', 'inviter']);
            }

            return TableGameInvite::create([
                'table_game_id' => $table->id,
                'inviter_id' => $sender->id,
                'invitee_id' => $invitee->id,
                'status' => 'pending',
                'expires_at' => $table->join_deadline_at ?? now()->addHours(24),
            ]);
        }, 3);
    }

    public function pendingInvites(User $user): \Illuminate\Support\Collection
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('table_game_invites')) return collect();
        return TableGameInvite::with(['table.currency', 'table.players.user', 'inviter'])
            ->where('invitee_id', $user->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->whereHas('table', function ($query) use ($user) {
                $query->where('status', 'waiting');
            })
            ->latest()
            ->limit(20)
            ->get()
            ->each(function ($invite) use ($user) {
                try {
                    $quote = $this->walletStakeQuoteFor($user, $invite->table);
                    $this->validateParticipantWalletStake($user, $quote['wallet_stake'], $invite->table);
                    $invite->wallet_stake_preview = $quote['wallet_stake'];
                    $invite->wallet_stake_available = true;
                } catch (InvalidArgumentException) {
                    $invite->wallet_stake_preview = null;
                    $invite->wallet_stake_available = false;
                }
            });
    }

    public function acceptInvite(User $user, int $inviteId): TableGame
    {
        return DB::transaction(function () use ($user, $inviteId) {
            $invite = TableGameInvite::whereKey($inviteId)->lockForUpdate()->firstOrFail();
            if ((int) $invite->invitee_id !== (int) $user->id) throw new InvalidArgumentException('This invitation belongs to another player.');
            if ($invite->status === 'accepted' && $invite->table->players()->where('user_id', $user->id)->exists()) return $invite->table;
            if ($invite->status !== 'pending' || !$invite->expires_at?->isFuture()) throw new InvalidArgumentException('This invitation has expired.');
            $table = $this->join($user, $invite->table_game_id);
            $invite->status = 'accepted';
            $invite->accepted_at = now();
            $invite->save();
            return $table;
        }, 3);
    }

    public function start(User $user, string $id): TableGame
    {
        return DB::transaction(function () use ($user, $id) {
            $table = TableGame::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->expireIfNeeded($table);
            if ($table->status !== 'waiting') return $table->fresh(['players.user']);
            if ((int) $table->creator_id !== (int) $user->id) throw new InvalidArgumentException('Only the table creator can start it early.');
            if ($table->status !== 'waiting' || $table->players()->where('status', 'seated')->count() < 2) throw new InvalidArgumentException('At least two seated players are required.');
            $this->startLocked($table);
            return $table->fresh(['players.user']);
        }, 3);
    }

    public function move(User $user, string $id, array $action, string $idempotencyKey, ?int $expectedVersion = null): TableGame
    {
        try {
            return DB::transaction(function () use ($user, $id, $action, $idempotencyKey, $expectedVersion) {
            if (!preg_match('/^[A-Za-z0-9_-]{12,64}$/', $idempotencyKey) || count($action) > 12 || strlen(json_encode($action) ?: '') > 4096) {
                throw new InvalidArgumentException('The move request is invalid.');
            }
            $table = TableGame::whereKey($id)->lockForUpdate()->firstOrFail();
            $priorMove = TableGameMove::where('idempotency_key', $idempotencyKey)->first();
            if ($priorMove) {
                if ($priorMove->table_game_id !== $table->id || (int) $priorMove->user_id !== (int) $user->id) throw new InvalidArgumentException('That request key is already in use.');
                return $table->fresh(['players.user']);
            }
            if ($table->status !== 'active') throw new InvalidArgumentException('This table is not in an active game.');
            if ($expectedVersion !== null && $expectedVersion !== (int) $table->version) throw new InvalidArgumentException('The game has changed. Reload the latest table state.');
            if ($table->turn_deadline_at && now()->greaterThan($table->turn_deadline_at)) {
                $this->recordMissedTurn($table);
                return $table->fresh(['players.user']);
            }
            $player = $table->players()->where('user_id', $user->id)->where('status', 'seated')->lockForUpdate()->first();
            if (!$player || (int) $table->current_user_id !== (int) $user->id) throw new InvalidArgumentException('It is not your turn at this table.');
                $activeSeats = $table->players()->where('status', 'seated')->pluck('seat')->map(fn ($seat) => (int) $seat)->all();
                $result = $this->rules->move($table->game_type, $table->state ?? [], (int) $player->seat, $action, $activeSeats);
            $table->state = $result['state'];
            $table->version++;
            $table->turn_started_at = now();
            $table->turn_deadline_at = now()->addSeconds((int) data_get($table->settings, 'turn_seconds', 60));
            $player->missed_turns = 0;
            $player->last_seen_at = now();
            $player->save();
            TableGameMove::create([
                'table_game_id' => $table->id,
                'user_id' => $user->id,
                'version' => $table->version,
                'action' => (($action['roll'] ?? false) === true) ? 'dice_roll' : 'move',
                'public_data' => ['move' => $action, 'roll' => data_get($table->state, 'roll') ?? data_get($table->state, 'last_roll')],
                'idempotency_key' => $idempotencyKey,
            ]);
            if ($result['winner'] !== null || $result['draw']) {
                $table->status = 'finished';
                $table->result = $result['draw'] ? 'draw' : (isset($result['winners']) ? 'winners:' . implode(',', $result['winners']) : 'winner:' . $result['winner']);
                $table->finished_at = now();
                $table->save();
                $this->settle($table, $result['winner'], $result['winners'] ?? null);
            } else {
                $nextSeat = (int) ($table->state['turn'] ?? -1);
                $nextPlayer = $table->players()->where('status', 'seated')->where('seat', $nextSeat)->first();
                if (!$nextPlayer) {
                    $table->current_user_id = $this->nextPlayerId($table, (int) $player->seat);
                    $state = $table->state ?? [];
                    $state['turn'] = (int) $table->players()->where('user_id', $table->current_user_id)->value('seat');
                    $table->state = $state;
                } else {
                    $table->current_user_id = (int) $nextPlayer->user_id;
                }
                $table->save();
            }
                return $table->fresh(['players.user']);
            }, 3);
        } catch (InvalidArgumentException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            try {
                $this->forceClose($id);
            } catch (Throwable $refundException) {
                report($refundException);
            }
            throw new RuntimeException('The game encountered a server error. The table was closed and any unsettled stakes were refunded.', 0, $exception);
        }
    }

    public function standUp(User $user, string $id): TableGame
    {
        return DB::transaction(function () use ($user, $id) {
            $table = TableGame::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->expireIfNeeded($table);
            if ($table->status === 'finished' && $table->result === 'expired_refunded') return $table->fresh(['players.user']);
            $player = $table->players()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if (!in_array($table->status, ['waiting', 'active'], true)) throw new InvalidArgumentException('This table is already closed.');
            if ($table->status === 'waiting' && (int) $table->creator_id === (int) $user->id) {
                $this->refundAll($table, 'creator_cancelled_waiting_table');
                $this->expireTableInvites($table);
                $table->status = 'finished';
                $table->result = 'cancelled_refunded';
                $table->finished_at = now();
                $table->version++;
                $table->save();
                return $table->fresh(['players.user']);
            }
            $player->status = 'out';
            if ($table->status === 'waiting') {
                if (bccomp((string) $player->stake, '0', 8) > 0) {
                    $this->creditPlayer($table, $player, (string) $player->stake, 'table_refund', 'player_left_waiting_table');
                }
                $player->status = 'refunded';
            }
            $player->save();
            $table->version++;
            $remaining = $table->players()->where('status', 'seated')->count();
            // Spades/Hearts need exactly 4 fixed partnership seats - the hand cannot continue or
            // be fairly scored with fewer, and the departed seat can never act again, so without
            // this the table would just stall forever with its stakes locked (see the activeSeats
            // fix in TableGameRules::blackjack()/holdem() for the games that CAN keep going short).
            if ($table->status === 'active' && in_array($table->game_type, ['spades', 'hearts'], true) && $remaining > 1 && $remaining < 4) {
                $table->status = 'finished';
                $table->result = 'cancelled_refunded';
                $table->finished_at = now();
                $table->save();
                $this->refundAll($table, 'partnership_game_broke_on_departure');
                return $table->fresh(['players.user']);
            }
            if ($table->status === 'active' && $remaining <= 1) {
                $winner = $table->players()->where('status', 'seated')->first();
                $table->status = 'finished';
                $table->result = $winner ? 'winner:' . $winner->seat : 'draw';
                $table->finished_at = now();
                $this->settle($table, $winner ? (int) $winner->seat : null);
            } elseif ($table->status === 'active' && (int) $table->current_user_id === (int) $user->id) {
                $table->current_user_id = $this->nextPlayerId($table, (int) $player->seat);
                if (is_array($table->state)) {
                    $state = $table->state;
                    $state['turn'] = (int) $table->players()->where('user_id', $table->current_user_id)->value('seat');
                    if ($table->game_type === 'ludo') $state['pending_roll'] = null;
                if ($table->game_type === 'backgammon') $state['dice'] = null;
                    $table->state = $state;
                }
                $table->turn_started_at = now();
                $table->turn_deadline_at = now()->addSeconds((int) data_get($table->settings, 'turn_seconds', 60));
            }
            $table->save();
            return $table->fresh(['players.user']);
        }, 3);
    }

    public function snapshot(User $user, string $id, ?int $sinceVersion = null): array
    {
        $table = TableGame::select(['id', 'game_type', 'status', 'result', 'player_limit', 'stake', 'version', 'state', 'current_user_id', 'turn_deadline_at', 'settings'])
            ->findOrFail($id);
        $participant = TableGamePlayer::where('table_game_id', $table->id)->where('user_id', $user->id)->first();
        abort_unless($participant, 403);

        // Only take the table lock when an actual deadline transition is due.
        if (($table->status === 'active' && $table->turn_deadline_at && now()->greaterThan($table->turn_deadline_at))
            || ($table->status === 'waiting' && $table->join_deadline_at && now()->greaterThan($table->join_deadline_at))) {
            DB::transaction(function () use ($user, $id) {
                $locked = TableGame::whereKey($id)->lockForUpdate()->firstOrFail();
                $this->expireIfNeeded($locked);
                abort_unless($locked->players()->where('user_id', $user->id)->exists(), 403);
                if ($locked->status === 'waiting') $this->expireIfNeeded($locked);
                elseif ($locked->status === 'active' && $locked->turn_deadline_at && now()->greaterThan($locked->turn_deadline_at)) $this->recordMissedTurn($locked);
            }, 3);
            $table->refresh();
        }

        if ($sinceVersion !== null && $sinceVersion === (int) $table->version) {
            return ['unchanged' => true, 'version' => (int) $table->version, 'status' => $table->status, 'turn_deadline_at' => $table->turn_deadline_at?->toIso8601String()];
        }
        $players = TableGamePlayer::with('user:id,username')->where('table_game_id', $table->id)->orderBy('seat')->get();
        $settlement = null;
        if ($table->status === 'finished') {
            // $participant may predate a settlement that just happened in this very request
            // (the deadline-transition lock above refreshes $table but not $participant).
            $own = $players->firstWhere('user_id', $user->id) ?? $participant->refresh();
            if ($own->settlement_type) {
                $stakeAmount = (string) ($own->wallet_stake ?? $own->stake);
                $netAmount = $own->settlement_type === 'payout'
                    ? bcsub((string) $own->settlement_amount, $stakeAmount, 8)
                    : ($own->settlement_type === 'loss' ? bcmul($stakeAmount, '-1', 8) : '0.00000000');
                $walletCurrency = $user->walletCurrency;
                $money = new \App\Support\Money();
                $format = fn ($value) => $walletCurrency
                    ? $walletCurrency->formatMinor($money->toMinor($value, (int) $walletCurrency->decimals), true)
                    : null;
                $settlement = [
                    'type' => $own->settlement_type,
                    'amount' => (string) $own->settlement_amount,
                    'amount_formatted' => $format((string) $own->settlement_amount),
                    'stake' => $stakeAmount,
                    'stake_formatted' => $format($stakeAmount),
                    'net' => $netAmount,
                    'net_formatted' => $format(ltrim($netAmount, '-')),
                ];
            }
        }
        $lastMove = null;
        if (in_array($table->game_type, ['chess', 'draughts'], true)) {
            $moveRow = TableGameMove::where('table_game_id', $table->id)->where('action', 'move')->latest('id')->first();
            $from = $moveRow?->public_data['move']['from'] ?? null;
            $to = $moveRow?->public_data['move']['to'] ?? null;
            if ($from && $to) $lastMove = ['from' => $from, 'to' => $to];
        }
        return [
            'id' => $table->id,
            'game' => $table->game_type,
            'status' => $table->status,
            'result' => $table->result,
            'player_limit' => $table->player_limit,
            'stake' => (string) $table->stake,
            'version' => (int) $table->version,
            'state' => $this->publicState($table->game_type, $table->state ?? [], (int) $participant->seat),
            'current_user_id' => $table->current_user_id,
            'turn_deadline_at' => $table->turn_deadline_at?->toIso8601String(),
            'settlement' => $settlement,
            'last_move' => $lastMove,
            'players' => $players->map(fn ($player) => ['id' => $player->user_id, 'name' => $player->user?->username, 'seat' => $player->seat, 'status' => $player->status])->values(),
        ];
    }

    public function liveTables(?User $user = null): array
    {
        $query = TableGame::with(['currency', 'players.user'])->whereIn('status', ['waiting', 'active']);
        return $query->latest()->limit(50)->get()->map(function ($table) use ($user) {
            $walletStake = null;
            $canJoin = true;
            if (bccomp((string) $table->stake, '0', 8) > 0 && $user) {
                try {
                    $walletStake = $this->walletStakeQuoteFor($user, $table)['wallet_stake'];
                    $this->validateParticipantWalletStake($user, $walletStake, $table);
                } catch (InvalidArgumentException) {
                    $canJoin = false;
                }
            }
            $currency = $user?->walletCurrency ?? $table->currency;
            return [
                'id' => $table->id, 'game' => $table->game_type, 'status' => $table->status,
                'stake' => (string) $table->stake, 'wallet_stake' => $walletStake, 'can_join' => $canJoin,
                'player_limit' => $table->player_limit,
                'currency_symbol' => $currency?->symbol ?? $table->currency?->symbol,
                'currency_code' => $currency?->code ?? $table->currency?->code,
                'wallet_stake_formatted' => $walletStake !== null && $currency
                    ? $currency->formatMinor((new Money())->toMinor($walletStake, (int) $currency->decimals), true)
                    : null,
                'players' => $table->players->where('status', 'seated')->map(fn ($player) => ['id' => (int) $player->user_id, 'name' => $player->user?->username, 'seat' => $player->seat])->values(),
            ];
        })->all();
    }

    public function sweepExpired(): void
    {
        $this->expireWaitingTables();
    }

    public function forceClose(string $id): TableGame
    {
        return DB::transaction(function () use ($id) {
            $table = TableGame::whereKey($id)->lockForUpdate()->firstOrFail();
            if (in_array($table->status, ['waiting', 'active'], true)) {
                $this->refundAll($table, 'admin_force_close_refund');
                $table->status = 'force_closed';
                $table->result = 'refunded';
                $table->finished_at = now();
            $table->version++;
                $table->save();
            }
            return $table;
        }, 3);
    }

    private function validateCreate(User $user, string $gameType, int $playerLimit, string $stake, TableGameSetting $setting): void
    {
        $definition = TableGameRules::GAMES[$gameType] ?? null;
        if (!$definition || !$setting->enabled) throw new InvalidArgumentException('This game is currently unavailable.');
        if (!in_array($gameType, TableGameRules::AVAILABLE, true)) throw new InvalidArgumentException('This game is not yet enabled for tables.');
        if ($playerLimit < $definition['min'] || $playerLimit > $definition['max']) throw new InvalidArgumentException('Select a supported player count.');
        if (bccomp($stake, '0', 8) < 0 || (bccomp($stake, '0', 8) > 0 && bccomp($stake, (string) $setting->min_stake, 8) < 0) || bccomp($stake, (string) $setting->max_stake, 8) > 0) throw new InvalidArgumentException('The stake is outside the allowed limits.');
        if (bccomp($stake, '0', 8) > 0) {
            $walletCurrency = Currency::find($user->currency_id);
            if (!$walletCurrency?->enabled) throw new InvalidArgumentException('Staked games are unavailable for your wallet currency.');
            $this->currentCurrencyRate($walletCurrency);
            (new Money())->toMinor($stake, (int) $walletCurrency->decimals);
        }
        if (bccomp($stake, '0', 8) > 0 && $user->country_id && Schema::hasTable('countries')) {
            $country = Country::with('currency')->find($user->country_id);
            if (!$country || !$country->is_enabled || !$country->is_allowed || !$country->currency?->enabled
                || (int) $country->currency_id !== (int) $user->currency_id) {
                throw new InvalidArgumentException('Your country or wallet currency is not enabled for staked tables.');
            }
            $minorStake = (new Money())->toMinor($stake, (int) $country->currency->decimals);
            if (($country->min_table_stake_minor > 0 && bccomp($minorStake, (string) $country->min_table_stake_minor, 0) < 0)
                || ($country->max_table_stake_minor > 0 && bccomp($minorStake, (string) $country->max_table_stake_minor, 0) > 0)) {
                throw new InvalidArgumentException('The stake is outside your country’s allowed limits.');
            }
        }
        $userLimit = TableGameUserLimit::where('user_id', $user->id)->first();
        if ($userLimit?->banned) throw new InvalidArgumentException('Your access to live tables is restricted.');
        $userMax = $userLimit?->max_open_tables ?? $setting->max_open_tables_per_user;
        $openTables = TableGame::whereIn('status', ['waiting', 'active'])->where(function ($query) use ($user) {
            $query->where('creator_id', $user->id)->orWhereHas('players', fn ($players) => $players->where('user_id', $user->id)->where('status', 'seated'));
        })->distinct('table_games.id')->count('table_games.id');
        if ($openTables >= (int) $userMax) throw new InvalidArgumentException('You have reached your open table limit.');
        if (TableGame::where('game_type', $gameType)->whereIn('status', ['waiting', 'active'])->count() >= (int) $setting->max_concurrent_tables) throw new InvalidArgumentException('The live table limit for this game has been reached.');
    }

    private function startLocked(TableGame $table): void
    {
        $players = $table->players()->where('status', 'seated')->orderBy('seat')->get();
        $table->state = $this->rules->start($table->game_type, $players->count());
        $table->status = 'active';
        $table->current_user_id = $players->first()->user_id;
        $table->started_at = now();
        $table->turn_started_at = now();
        $table->turn_deadline_at = now()->addSeconds((int) data_get($table->settings, 'turn_seconds', 60));
        $table->version++;
        $table->save();
    }

    private function debit(User $user, TableGame $table, string $reason, ?string $amount = null): void
    {
        $amount = $amount ?? (string) $table->stake;
        if (bccomp($amount, '0', 8) === 0) return;
        if (bccomp((string) $user->balance, $amount, 8) < 0) throw new InvalidArgumentException('Your available balance is too low for this stake.');
        $user->balance = bcsub((string) $user->balance, $amount, 8);
        $user->save();
        $this->ledger($user, $table, $amount, '-', 'table_stake', $reason);
    }

    private function ledger(User $user, TableGame $table, string $amount, string $type, string $remark, string $details): void
    {
        $transaction = new Transaction();
        $transaction->user_id = $user->id;
        $transaction->amount = $amount;
        $transaction->charge = 0;
        $transaction->trx_type = $type;
        $transaction->details = ucfirst(str_replace('_', ' ', $details)) . ' for ' . (TableGameRules::GAMES[$table->game_type]['name'] ?? $table->game_type);
        $transaction->remark = $remark;
        $transaction->trx = strtoupper(Str::random(12));
        $transaction->post_balance = $user->balance;
        if ($this->hasColumn('transactions', 'currency_id')) $transaction->currency_id = $user->currency_id;
        $transaction->save();
    }

    private function walletStakeQuoteFor(User $user, TableGame $table): array
    {
        if (bccomp((string) $table->stake, '0', 8) === 0) {
            return ['wallet_stake' => '0.00000000', 'wallet_rate_to_usd' => null, 'table_rate_to_usd' => null];
        }
        if (!$table->currency_id || !$user->currency_id) throw new InvalidArgumentException('A supported wallet currency is required for staked games.');

        $tableCurrency = Currency::find($table->currency_id);
        $walletCurrency = Currency::find($user->currency_id);
        if (!$tableCurrency?->enabled || !$walletCurrency?->enabled) throw new InvalidArgumentException('Staked games require enabled wallet currencies.');

        $tableRate = $this->currentCurrencyRate($tableCurrency);
        $walletRate = $this->currentCurrencyRate($walletCurrency);
        if ((string) $table->currency_id === (string) $user->currency_id) {
            return ['wallet_stake' => (string) $table->stake, 'wallet_rate_to_usd' => $walletRate, 'table_rate_to_usd' => $tableRate];
        }
        $precision = max(12, (int) $walletCurrency->decimals + 2);
        $walletStake = bcdiv(bcmul((string) $table->stake, $tableRate, $precision), $walletRate, $precision);
        $walletStake = $this->roundCurrencyAmount($walletStake, (int) $walletCurrency->decimals);
        if (bccomp($walletStake, '0', 8) <= 0) throw new InvalidArgumentException('This stake is too small to convert into your wallet currency.');

        return ['wallet_stake' => $walletStake, 'wallet_rate_to_usd' => (string) $walletRate, 'table_rate_to_usd' => (string) $tableRate];
    }

    private function currentCurrencyRate(Currency $currency): string
    {
        if (!$currency->enabled || !$currency->rate_to_usd || !$currency->last_updated_at
            || $currency->last_updated_at->lt(now()->subHours(48))) {
            throw new InvalidArgumentException('A current exchange rate is not available for this currency. Please try again after the daily rate refresh.');
        }
        return (string) $currency->rate_to_usd;
    }

    private function roundCurrencyAmount(string $amount, int $decimals): string
    {
        $decimals = min(8, max(0, $decimals));
        $halfUnit = '0.' . str_repeat('0', $decimals) . '5';
        $rounded = bcadd($amount, $halfUnit, max(12, $decimals + 1));
        return bcadd($rounded, '0', $decimals);
    }

    private function validateParticipantWalletStake(User $user, string $walletStake, TableGame $table): void
    {
        if (bccomp((string) $table->stake, '0', 8) === 0) return;
        $walletCurrency = Currency::find($user->currency_id);
        if (!$walletCurrency?->enabled) throw new InvalidArgumentException('Staked games are unavailable for your wallet currency.');
        if (!$user->country_id || !Schema::hasTable('countries')) return;
        $country = Country::with('currency')->find($user->country_id);
        if (!$country || !$country->is_enabled || !$country->is_allowed || !$country->currency?->enabled
            || (int) $country->currency_id !== (int) $user->currency_id) {
            throw new InvalidArgumentException('Staked tables are not available for your country or wallet currency.');
        }
        $minorStake = (new Money())->toMinor($walletStake, (int) $country->currency->decimals);
        if (($country->min_table_stake_minor > 0 && bccomp($minorStake, (string) $country->min_table_stake_minor, 0) < 0)
            || ($country->max_table_stake_minor > 0 && bccomp($minorStake, (string) $country->max_table_stake_minor, 0) > 0)) {
            throw new InvalidArgumentException('The converted stake is outside your country’s allowed limits.');
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        return $this->schemaColumnCache[$key] ??= Schema::hasColumn($table, $column);
    }

    private function settle(TableGame $table, ?int $winnerSeat, ?array $winnerSeats = null): void
    {
        if (bccomp((string) $table->stake, '0', 8) === 0) return;
        $players = $table->players()->lockForUpdate()->get();
        if ($winnerSeat === null) {
            foreach (TableGameSettlementMath::refunds($players->mapWithKeys(fn ($player) => [$player->user_id => (string) $player->stake])->all()) as $userId => $amount) {
                $player = $players->firstWhere('user_id', $userId);
                $this->creditPlayer($table, $player, $amount, 'table_refund', 'draw_refund');
            }
            return;
        }
        if ($winnerSeats) {
            $winners = $players->filter(fn ($player) => in_array((int) $player->seat, $winnerSeats, true) && $player->status !== 'refunded')->values();
            if ($winners->count() > 1) {
                $rate = (string) data_get($table->settings, 'commission_percent', '5.00');
                $payout = TableGameSettlementMath::payout($players->pluck('stake')->map(fn ($stake) => (string) $stake)->all(), $rate);
                $share = bcdiv($payout['winner_amount'], (string) $winners->count(), 8);
                $paid = '0.00000000';
                foreach ($winners as $index => $winner) {
                    $amount = $index === $winners->count() - 1 ? bcsub($payout['winner_amount'], $paid, 8) : $share;
                    $this->creditPlayer($table, $winner, $amount, 'table_payout', 'winner_payout');
                    $paid = bcadd($paid, $amount, 8);
                }
                if (bccomp($payout['commission'], '0', 8) > 0) {
                    TableGameCommission::create(['table_game_id' => $table->id, 'game_type' => $table->game_type, 'amount' => $payout['commission'], 'rate_percent' => data_get($table->settings, 'commission_percent', 5), 'status' => 'retained']);
                }
                $this->markLosers($table, $players, $winners->pluck('user_id')->all());
                return;
            }
            $winnerSeat = $winners->first()?->seat;
        }
        $winner = $players->firstWhere('seat', $winnerSeat);
        if (!$winner || $winner->status === 'refunded') {
            foreach (TableGameSettlementMath::refunds($players->mapWithKeys(fn ($player) => [$player->user_id => (string) $player->stake])->all()) as $userId => $amount) {
                $player = $players->firstWhere('user_id', $userId);
                $this->creditPlayer($table, $player, $amount, 'table_refund', 'invalid_winner_refund');
            }
            return;
        }
        $rate = (string) data_get($table->settings, 'commission_percent', '5.00');
        $payout = TableGameSettlementMath::payout($players->pluck('stake')->map(fn ($stake) => (string) $stake)->all(), $rate);
        $this->creditPlayer($table, $winner, $payout['winner_amount'], 'table_payout', 'winner_payout');
        $commission = $payout['commission'];
        if (bccomp($commission, '0', 8) > 0) {
            TableGameCommission::create([
                'table_game_id' => $table->id,
                'game_type' => $table->game_type,
                'amount' => $commission,
                'rate_percent' => data_get($table->settings, 'commission_percent', 5),
                'status' => 'retained',
            ]);
        }
        $this->markLosers($table, $players, [$winner->user_id]);
    }

    private function creditPlayer(TableGame $table, TableGamePlayer $player, string $amount, string $remark, string $reason): void
    {
        if (bccomp($amount, '0', 8) <= 0) return;
        if ($player->settlement_key) return;
        $user = User::whereKey($player->user_id)->lockForUpdate()->firstOrFail();
        $walletStake = (string) ($player->wallet_stake ?? $player->stake);
        $walletAmount = bccomp((string) $player->stake, '0', 8) > 0
            ? bcdiv(bcmul($amount, $walletStake, 20), (string) $player->stake, 12)
            : $amount;
        $currency = Currency::find($player->currency_id ?? $user->currency_id);
        $decimals = min(8, max(0, (int) ($currency?->decimals ?? 2)));
        $walletAmount = bcadd(bcadd($walletAmount, '0.' . str_repeat('0', $decimals) . '5', max(12, $decimals + 1)), '0', $decimals);
        $user->balance = bcadd((string) $user->balance, $walletAmount, 8);
        $user->save();
        $player->settlement_key = $table->id . ':settle:' . $user->id;
        $player->settlement_amount = $walletAmount;
        $player->settlement_type = $remark === 'table_refund' ? 'refund' : 'payout';
        $player->save();
        $this->ledger($user, $table, $walletAmount, '+', $remark, $reason);
    }

    /**
     * Mark every seated/out player NOT in $winnerUserIds as having lost their stake, so the
     * client can show a server-authoritative "you lost $X" result without inferring it from
     * the absence of a payout row.
     */
    private function markLosers(TableGame $table, $players, array $winnerUserIds): void
    {
        foreach ($players as $player) {
            if ($player->settlement_key) continue;
            if (in_array($player->user_id, $winnerUserIds, true)) continue;
            if (!in_array($player->status, ['seated', 'out'], true)) continue;
            $player->settlement_key = $table->id . ':settle:' . $player->user_id;
            $player->settlement_amount = '0.00000000';
            $player->settlement_type = 'loss';
            $player->save();
        }
    }

    private function refundAll(TableGame $table, string $reason): void
    {
        $players = $table->players()->lockForUpdate()->get();
        foreach (TableGameSettlementMath::refunds($players->mapWithKeys(fn ($player) => [$player->user_id => (string) $player->stake])->all()) as $userId => $amount) {
            $this->creditPlayer($table, $players->firstWhere('user_id', $userId), $amount, 'table_refund', $reason);
        }
        $table->players()->whereIn('status', ['seated', 'out'])->update(['status' => 'refunded']);
    }

    private function recordMissedTurn(TableGame $table): void
    {
        $player = $table->players()->where('user_id', $table->current_user_id)->lockForUpdate()->first();
        if (!$player) throw new RuntimeException('The active seat could not be found.');
        $player->missed_turns++;
        if ($player->missed_turns >= 2) $player->status = 'out';
        $player->save();
        $priorVersion = (int) $table->version;
        $table->version++;
        TableGameMove::create([
            'table_game_id' => $table->id,
            'user_id' => $player->user_id,
            'version' => $table->version,
            'action' => 'missed_turn',
            'public_data' => ['missed_turns' => (int) $player->missed_turns, 'player_out' => $player->status === 'out'],
            'idempotency_key' => $table->id . ':timeout:' . $priorVersion,
        ]);
        $remaining = $table->players()->where('status', 'seated')->count();
        if (in_array($table->game_type, ['spades', 'hearts'], true) && $remaining > 1 && $remaining < 4) {
            $table->status = 'finished';
            $table->result = 'cancelled_refunded';
            $table->finished_at = now();
            $table->save();
            $this->refundAll($table, 'partnership_game_broke_on_timeout');
            return;
        }
        if ($remaining <= 1) {
            $winner = $table->players()->where('status', 'seated')->first();
            $table->status = 'finished';
            $table->result = $winner ? 'winner:' . $winner->seat : 'draw';
            $table->finished_at = now();
            $table->save();
            $this->settle($table, $winner ? (int) $winner->seat : null);
            return;
        }
        $table->current_user_id = $this->nextPlayerId($table, (int) $player->seat);
        if (is_array($table->state)) {
            $state = $table->state;
            $state['turn'] = (int) $table->players()->where('user_id', $table->current_user_id)->value('seat');
            if ($table->game_type === 'ludo') $state['pending_roll'] = null;
            $table->state = $state;
        }
        $table->turn_started_at = now();
        $table->turn_deadline_at = now()->addSeconds((int) data_get($table->settings, 'turn_seconds', 60));
        $table->save();
    }

    private function nextPlayerId(TableGame $table, int $afterSeat): int
    {
        $next = $table->players()->where('status', 'seated')->where('seat', '>', $afterSeat)->orderBy('seat')->first();
        $next ??= $table->players()->where('status', 'seated')->orderBy('seat')->first();
        if (!$next) throw new RuntimeException('No active player remains.');
        return (int) $next->user_id;
    }

    private function expireIfNeeded(TableGame $table): void
    {
        if ($table->status === 'waiting' && $table->join_deadline_at && now()->greaterThan($table->join_deadline_at)) {
            $this->refundAll($table, 'empty_table_refund');
            $this->expireTableInvites($table);
            $table->status = 'finished';
            $table->result = 'expired_refunded';
            $table->finished_at = now();
            $table->version++;
            $table->save();
        }
    }

    private function expireTableInvites(TableGame $table): void
    {
        if (!Schema::hasTable('table_game_invites')) return;
        TableGameInvite::where('table_game_id', $table->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired', 'expires_at' => now(), 'updated_at' => now()]);
    }

    private function expireWaitingTables(): void
    {
        TableGame::where('status', 'waiting')->where('join_deadline_at', '<', now())->limit(100)->pluck('id')->each(function ($id) {
            DB::transaction(function () use ($id) {
                $table = TableGame::whereKey($id)->lockForUpdate()->first();
                if ($table) $this->expireIfNeeded($table);
            }, 3);
        });
        TableGame::where('status', 'active')->where('turn_deadline_at', '<', now())->limit(100)->pluck('id')->each(function ($id) {
            DB::transaction(function () use ($id) {
                $table = TableGame::whereKey($id)->lockForUpdate()->first();
                if ($table && $table->status === 'active' && $table->turn_deadline_at && now()->greaterThan($table->turn_deadline_at)) $this->recordMissedTurn($table);
            }, 3);
        });
    }

    private function setting(string $gameType): TableGameSetting
    {
        $defaults = [
            'enabled' => true, 'commission_percent' => 5, 'min_stake' => 1, 'max_stake' => 1000,
            'turn_seconds' => 60, 'empty_timeout_minutes' => 1440, 'max_open_tables_per_user' => 5, 'max_concurrent_tables' => 500,
        ];
        $global = TableGameSetting::firstOrNew(['game_type' => '*'], $defaults);
        return TableGameSetting::firstOrNew(['game_type' => $gameType], array_merge($defaults, $global->exists ? $global->only(array_keys($defaults)) : []));
    }

    private function drawRule(string $game): string
    {
        return match ($game) {
            'tic_tac_toe', 'four_in_a_row' => 'A full board without a winner is a draw; every stake is refunded.',
            'oware' => 'When the round ends, all remaining seeds are counted with captured seeds. Equal final scores draw and all stakes are refunded.',
            'snakes_and_ladders' => 'No draw; first player to land exactly on square 100 wins.',
            'ludo' => 'No draw in a normal game; first player to finish all four tokens wins. A table timeout or force-close refunds stakes.',
            'chess' => 'Stalemate, an accepted draw offer, insufficient material, or 100 half-moves without a pawn move or capture is a draw; stakes are refunded.',
            'draughts' => 'A draw is declared after 80 consecutive half-moves without a capture or promotion; stakes are refunded.',
            'dominoes' => 'Play until one hand is empty. If the stock is empty and every player passes, the player with the fewest pips wins; equal low totals draw.',
            'crazy_eights' => 'A draw is declared only when the draw and discard piles are exhausted and every player is blocked; all stakes are refunded.',
            default => 'Game-specific rules determine whether a draw is possible.',
        };
    }

    /** Return only information the requesting seat is entitled to see. */
    private function publicState(string $game, array $state, int $seat): array
    {
        if (isset($state['hands']) && is_array($state['hands'])) {
            $state['hand_counts'] = array_map('count', $state['hands']);
            if ($game === 'blackjack') {
                $state['hand_totals'] = array_map(function ($hand) {
                    $total = 0; $aces = 0;
                    foreach ($hand as $card) { if ($card['rank'] === 'A') { $total += 11; $aces++; } elseif (in_array($card['rank'], ['J','Q','K'], true)) $total += 10; else $total += (int) $card['rank']; }
                    while ($total > 21 && $aces > 0) { $total -= 10; $aces--; }
                    return $total;
                }, $state['hands']);
            }
            foreach ($state['hands'] as $owner => $hand) {
                if ((int) $owner !== $seat) $state['hands'][$owner] = [];
            }
        }

        if (in_array($game, ['texas_holdem', 'poker'], true) && isset($state['private_hands'])) {
            $state['private_hand'] = $state['private_hands'][$seat] ?? [];
            unset($state['private_hands']);
        }

        if ($game === 'sea_battle' && isset($state['boards'])) {
            $state['own_board'] = $state['boards'][$seat] ?? [];
            $state['my_shots'] = $state['shots'][$seat] ?? [];
            $state['incoming_shots'] = $state['shots'][1 - $seat] ?? [];
            unset($state['boards']);
        }

        if (in_array($game, ['blackjack'], true) && !($state['dealer_revealed'] ?? false) && isset($state['dealer_hand'])) {
            $state['dealer_hand'] = array_slice($state['dealer_hand'], 0, 1);
        }

        return $state;
    }
}
