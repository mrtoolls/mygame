<?php

namespace App\Services\Fantasy;

use App\Models\FantasyContest;
use App\Models\FantasyContestEntry;
use App\Models\FantasyTeam;
use App\Models\FantasyTeamGameweekHistory;
use App\Models\User;
use App\Services\CoinLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Coin-staked fantasy contests (head-to-head, many-entrant gameweek contests, and free sponsored
 * prize leagues), built the same way TableGameService settles a pooled stake: a stake is HELD
 * (never debited) in the entrant's own wallet at entry, and settlement moves coins directly
 * between entrants' held balances via CoinLedger::settleHeldToOwner() - winners release their own
 * stake back plus draw the rest of their payout from losers' held balances, and whatever is left
 * over after every winner is paid is, by construction, exactly the fee, swept to the platform
 * wallet (the same "coins never left a real wallet, so a settleHeldToOwner to platform IS the
 * burn" reasoning TableGameService::settleCoins() already documents). A sponsored contest never
 * holds anything (entry_fee is always 0) - its payouts mint new promotional coins at settlement.
 */
class FantasyContestService
{
    public function enter(User $user, FantasyContest $contest, FantasyTeam $team): FantasyContestEntry
    {
        return DB::transaction(function () use ($user, $contest, $team) {
            $contest = FantasyContest::whereKey($contest->id)->lockForUpdate()->firstOrFail();

            if (!gs('ff_enabled')) throw new InvalidArgumentException('Fantasy contests are currently unavailable.');
            if ($contest->status !== 'open') throw new InvalidArgumentException('This contest is no longer open for entries.');
            if ($contest->isDeadlinePassed()) throw new InvalidArgumentException('Entries have closed for this contest.');
            if ((int) $team->user_id !== (int) $user->id) throw new InvalidArgumentException('That is not your team.');
            if ((int) $team->season_id !== (int) $contest->gameweek->season_id) throw new InvalidArgumentException('That team does not belong to this contest\'s season.');
            if ($contest->max_entrants && FantasyContestEntry::where('contest_id', $contest->id)->count() >= $contest->max_entrants) {
                throw new InvalidArgumentException('This contest is full.');
            }
            if (FantasyContestEntry::where('contest_id', $contest->id)->where('user_id', $user->id)->exists()) {
                throw new InvalidArgumentException('You have already entered this contest.');
            }

            $stake = (string) $contest->entry_fee;
            $ip = request()?->ip();
            if (bccomp($stake, '0', 8) > 0) {
                $this->assertStakeCaps($user, $stake);
                $this->assertNoLinkedEntry($contest, $ip);
            }

            $idempotencyKey = 'ff_contest:' . $contest->id . ':entry:' . $user->id;
            if (bccomp($stake, '0', 8) > 0) {
                app(CoinLedger::class)->hold('user', $user->id, $stake, 'Fantasy contest entry: ' . $contest->name, $idempotencyKey, 'FantasyContest', $contest->id);
            }

            return FantasyContestEntry::create([
                'contest_id' => $contest->id, 'user_id' => $user->id, 'team_id' => $team->id,
                'stake' => $stake, 'hold_idempotency_key' => $idempotencyKey, 'status' => 'held',
                'entry_ip' => $ip, 'entered_at' => now(),
            ]);
        }, 3);
    }

    private function assertStakeCaps(User $user, string $stake): void
    {
        $maxEntry = (string) (gs('ff_max_entry_stake') ?? '10000');
        if (bccomp($stake, $maxEntry, 8) > 0) throw new InvalidArgumentException('That stake exceeds the maximum allowed per entry.');

        $dailyCap = (string) (gs('ff_daily_stake_cap') ?? '50000');
        $todayStaked = (string) FantasyContestEntry::where('user_id', $user->id)
            ->where('entered_at', '>=', now()->startOfDay())->sum('stake');
        if (bccomp(bcadd($todayStaked, $stake, 8), $dailyCap, 8) > 0) {
            throw new InvalidArgumentException('You have reached your daily contest staking limit.');
        }
    }

    /** Best-effort same-connection guard: no existing device/IP-fingerprint infrastructure exists in this codebase to check against, so this only blocks a second entry into the SAME contest from the same request IP. */
    private function assertNoLinkedEntry(FantasyContest $contest, ?string $ip): void
    {
        if (!$ip) return;
        if (FantasyContestEntry::where('contest_id', $contest->id)->where('entry_ip', $ip)->exists()) {
            throw new InvalidArgumentException('Only one entry per contest is allowed from the same connection.');
        }
    }

    /** Called by FantasyGameweekFinalizer once a locked contest's review window has passed. */
    public function settle(FantasyContest $contest): void
    {
        DB::transaction(function () use ($contest) {
            $contest = FantasyContest::whereKey($contest->id)->lockForUpdate()->first();
            if (!$contest || $contest->status !== 'locked') return;

            $entries = FantasyContestEntry::where('contest_id', $contest->id)->lockForUpdate()->get();

            if ($entries->count() < $contest->min_entrants) {
                $this->refundAll($contest, $entries);
                return;
            }

            foreach ($entries as $entry) {
                $history = FantasyTeamGameweekHistory::where('team_id', $entry->team_id)->where('gameweek_id', $contest->gameweek_id)->first();
                $entry->score = $history->points ?? 0;
                $entry->save();
            }

            $ranked = $entries->sortByDesc('score')->values();

            if ($contest->type === 'h2h') {
                $this->settleHeadToHead($contest, $ranked);
            } else {
                $this->settleTiered($contest, $ranked);
            }

            $contest->status = 'settled';
            $contest->settled_at = now();
            $contest->save();
        }, 3);
    }

    private function refundAll(FantasyContest $contest, $entries): void
    {
        $ledger = app(CoinLedger::class);
        foreach ($entries as $entry) {
            if (bccomp((string) $entry->stake, '0', 8) > 0) {
                $ledger->release('user', $entry->user_id, (string) $entry->stake, 'Fantasy contest refund: under minimum entrants', 'ff_contest:' . $contest->id . ':refund:' . $entry->user_id, 'FantasyContest', $contest->id);
            }
            $entry->status = 'refunded';
            $entry->payout = $entry->stake;
            $entry->save();
        }
        $contest->status = 'refunded';
        $contest->settled_at = now();
        $contest->final_pool = '0';
        $contest->final_fee_burned = '0';
        $contest->save();
    }

    private function settleHeadToHead(FantasyContest $contest, $ranked): void
    {
        $ledger = app(CoinLedger::class);
        [$a, $b] = [$ranked[0], $ranked[1]];
        $pot = bcadd((string) $a->stake, (string) $b->stake, 8);

        if ((int) $a->score === (int) $b->score) {
            foreach ([$a, $b] as $entry) {
                if (bccomp((string) $entry->stake, '0', 8) > 0) {
                    $ledger->release('user', $entry->user_id, (string) $entry->stake, 'Fantasy H2H tie refund', 'ff_contest:' . $contest->id . ':settle:' . $entry->user_id, 'FantasyContest', $contest->id);
                }
                $entry->status = 'refunded';
                $entry->payout = $entry->stake;
                $entry->save();
            }
            $contest->final_pool = $pot;
            $contest->final_fee_burned = '0';
            return;
        }

        $winner = $a;
        $loser = $b;
        $feePercent = (string) ($contest->fee_percent ?? gs('ff_contest_fee_percent') ?? '5');
        $fee = bcdiv(bcmul($pot, $feePercent, 10), '100', 8);
        $payout = bcsub($pot, $fee, 8);

        if (bccomp((string) $winner->stake, '0', 8) > 0) {
            $ledger->release('user', $winner->user_id, (string) $winner->stake, 'Fantasy H2H stake release', 'ff_contest:' . $contest->id . ':release:' . $winner->user_id, 'FantasyContest', $contest->id);
        }
        $owed = bcsub($payout, (string) $winner->stake, 8);
        if (bccomp($owed, '0', 8) > 0) {
            $ledger->settleHeldToOwner('user', $loser->user_id, 'user', $winner->user_id, $owed, 'Fantasy H2H payout', 'ff_contest:' . $contest->id . ':payout:' . $loser->user_id . ':' . $winner->user_id, 'FantasyContest', $contest->id);
        }
        $feeFromLoser = bcsub((string) $loser->stake, $owed, 8);
        if (bccomp($feeFromLoser, '0', 8) > 0) {
            $ledger->settleHeldToOwner('user', $loser->user_id, 'platform', CoinLedger::PLATFORM_OWNER_ID, $feeFromLoser, 'Fantasy contest fee', 'ff_contest:' . $contest->id . ':fee:' . $loser->user_id, 'FantasyContest', $contest->id);
        }

        $winner->status = 'settled';
        $winner->payout = $payout;
        $winner->save();
        $loser->status = 'settled';
        $loser->payout = '0.00000000';
        $loser->save();

        $contest->final_pool = $pot;
        $contest->final_fee_burned = $fee;
    }

    private function settleTiered(FantasyContest $contest, $ranked): void
    {
        $ledger = app(CoinLedger::class);
        $n = $ranked->count();
        $isSponsored = $contest->type === 'sponsored';

        if ($isSponsored) {
            $pot = (string) ($contest->sponsored_pool ?? '0');
            $fee = '0.00000000';
            $distributable = $pot;
        } else {
            $pot = '0.00000000';
            foreach ($ranked as $entry) $pot = bcadd($pot, (string) $entry->stake, 8);
            $feePercent = (string) ($contest->fee_percent ?? gs('ff_contest_fee_percent') ?? '5');
            $fee = bcdiv(bcmul($pot, $feePercent, 10), '100', 8);
            $distributable = bcsub($pot, $fee, 8);
        }

        $tiers = $contest->prize_structure ?: [['top_percent' => 100, 'share_percent' => 100]];
        $positionPayouts = FantasyContestPayoutMath::tieredPayouts($n, $tiers, $distributable);

        $tieGroups = [];
        foreach ($ranked->values() as $index => $entry) {
            $tieGroups[(string) $entry->score][] = $index + 1;
        }
        $averaged = FantasyContestPayoutMath::averageTiedPositions($positionPayouts, array_values($tieGroups));

        if ($isSponsored) {
            foreach ($ranked->values() as $index => $entry) {
                $payout = $averaged[$index + 1] ?? '0.00000000';
                if (bccomp($payout, '0', 8) > 0) {
                    $ledger->mint('user', $entry->user_id, $payout, 'Sponsored fantasy contest prize: ' . $contest->name, 'ff_contest:' . $contest->id . ':prize:' . $entry->user_id, 'FantasyContest', $contest->id);
                }
                $entry->status = 'settled';
                $entry->payout = $payout;
                $entry->save();
            }
            $contest->final_pool = $pot;
            $contest->final_fee_burned = '0';
            return;
        }

        $winners = []; // user_id => amount still owed beyond their own released stake
        $payers = [];  // user_id => held amount available to drain (their stake minus whatever they kept)
        foreach ($ranked->values() as $index => $entry) {
            $payout = $averaged[$index + 1] ?? '0.00000000';
            $stake = (string) $entry->stake;
            $net = bcsub($payout, $stake, 8);

            if (bccomp($net, '0', 8) >= 0) {
                if (bccomp($stake, '0', 8) > 0) {
                    $ledger->release('user', $entry->user_id, $stake, 'Fantasy contest stake release', 'ff_contest:' . $contest->id . ':release:' . $entry->user_id, 'FantasyContest', $contest->id);
                }
                if (bccomp($net, '0', 8) > 0) $winners[(int) $entry->user_id] = $net;
            } else {
                $keep = $payout;
                if (bccomp($keep, '0', 8) > 0) {
                    $ledger->release('user', $entry->user_id, $keep, 'Fantasy contest partial stake release', 'ff_contest:' . $contest->id . ':release:' . $entry->user_id, 'FantasyContest', $contest->id);
                }
                $toDrain = bcsub($stake, $keep, 8);
                if (bccomp($toDrain, '0', 8) > 0) $payers[(int) $entry->user_id] = $toDrain;
            }
            $entry->status = 'settled';
            $entry->payout = $payout;
            $entry->save();
        }

        $winnerIds = array_keys($winners);
        $cursor = 0;
        foreach ($payers as $payerId => $available) {
            $remaining = $available;
            while (bccomp($remaining, '0', 8) > 0 && $cursor < count($winnerIds)) {
                $winnerId = $winnerIds[$cursor];
                $need = $winners[$winnerId];
                if (bccomp($need, '0', 8) <= 0) { $cursor++; continue; }
                $give = bccomp($need, $remaining, 8) <= 0 ? $need : $remaining;
                if (bccomp($give, '0', 8) > 0) {
                    $ledger->settleHeldToOwner('user', $payerId, 'user', $winnerId, $give, 'Fantasy contest payout', 'ff_contest:' . $contest->id . ':payout:' . $payerId . ':' . $winnerId, 'FantasyContest', $contest->id);
                }
                $winners[$winnerId] = bcsub($need, $give, 8);
                $remaining = bcsub($remaining, $give, 8);
                if (bccomp($winners[$winnerId], '0', 8) <= 0) $cursor++;
            }
            // By construction (distributable == pot - fee), whatever remains after every winner
            // is satisfied is exactly this payer's share of the fee.
            if (bccomp($remaining, '0', 8) > 0) {
                $ledger->settleHeldToOwner('user', $payerId, 'platform', CoinLedger::PLATFORM_OWNER_ID, $remaining, 'Fantasy contest fee', 'ff_contest:' . $contest->id . ':fee:' . $payerId, 'FantasyContest', $contest->id);
            }
        }

        $contest->final_pool = $pot;
        $contest->final_fee_burned = $fee;
    }
}
