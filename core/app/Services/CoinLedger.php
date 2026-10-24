<?php

namespace App\Services;

use App\Models\CoinLedgerEntry;
use App\Models\CoinWallet;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single place every coin balance mutation in this codebase goes through. No other code may
 * write to coin_wallets or coin_ledger_entries directly - every method here locks the wallet
 * row(s) involved, re-checks balances under that lock, and writes an immutable ledger row before
 * returning. Every method is idempotent: calling it twice with the same idempotency key is a safe
 * no-op the second time (returns the original entry rather than mutating anything again), so a
 * retried HTTP request or a replayed queue job can never double-apply a coin movement.
 *
 * The 'platform' owner (owner_id 1) is the mint/burn counterparty: its available_balance is
 * allowed to go negative, representing total coins currently in circulation (a liability, not an
 * asset). Every other wallet (owner_type 'user' or 'agent') must stay non-negative - that is
 * enforced here, under lock, not by a database constraint.
 */
class CoinLedger
{
    public const PLATFORM_OWNER_ID = 1;

    public function wallet(string $ownerType, int $ownerId): CoinWallet
    {
        return CoinWallet::firstOrCreate(
            ['owner_type' => $ownerType, 'owner_id' => $ownerId],
            ['available_balance' => 0, 'held_balance' => 0]
        );
    }

    public function credit(string $ownerType, int $ownerId, string $amount, string $reason, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null, string $actorType = 'system', ?int $actorId = null): CoinLedgerEntry
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($ownerType, $ownerId, $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId) {
            if ($existing = $this->existingEntry($idempotencyKey)) return $existing;

            $wallet = $this->lockedWallet($ownerType, $ownerId);
            $wallet->available_balance = bcadd((string) $wallet->available_balance, $amount, 8);
            $wallet->save();

            return $this->writeEntry($wallet, 'credit', $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId);
        });
    }

    public function debit(string $ownerType, int $ownerId, string $amount, string $reason, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null, string $actorType = 'system', ?int $actorId = null): CoinLedgerEntry
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($ownerType, $ownerId, $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId) {
            if ($existing = $this->existingEntry($idempotencyKey)) return $existing;

            $wallet = $this->lockedWallet($ownerType, $ownerId);
            if ($ownerType !== 'platform' && bccomp((string) $wallet->available_balance, $amount, 8) < 0) {
                throw new InvalidArgumentException('Insufficient coin balance.');
            }
            $wallet->available_balance = bcsub((string) $wallet->available_balance, $amount, 8);
            $wallet->save();

            return $this->writeEntry($wallet, 'debit', $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId);
        });
    }

    /** Moves coins from available to held - e.g. a marketplace listing taking coins off the market while it is live. */
    public function hold(string $ownerType, int $ownerId, string $amount, string $reason, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null, string $actorType = 'system', ?int $actorId = null): CoinLedgerEntry
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($ownerType, $ownerId, $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId) {
            if ($existing = $this->existingEntry($idempotencyKey)) return $existing;

            $wallet = $this->lockedWallet($ownerType, $ownerId);
            if (bccomp((string) $wallet->available_balance, $amount, 8) < 0) {
                throw new InvalidArgumentException('Insufficient available coin balance to hold.');
            }
            $wallet->available_balance = bcsub((string) $wallet->available_balance, $amount, 8);
            $wallet->held_balance = bcadd((string) $wallet->held_balance, $amount, 8);
            $wallet->save();

            return $this->writeEntry($wallet, 'hold', $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId);
        });
    }

    /** Moves coins from held back to available - cancelling a listing, or a trade falling through. */
    public function release(string $ownerType, int $ownerId, string $amount, string $reason, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null, string $actorType = 'system', ?int $actorId = null): CoinLedgerEntry
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($ownerType, $ownerId, $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId) {
            if ($existing = $this->existingEntry($idempotencyKey)) return $existing;

            $wallet = $this->lockedWallet($ownerType, $ownerId);
            if (bccomp((string) $wallet->held_balance, $amount, 8) < 0) {
                throw new InvalidArgumentException('Insufficient held coin balance to release.');
            }
            $wallet->held_balance = bcsub((string) $wallet->held_balance, $amount, 8);
            $wallet->available_balance = bcadd((string) $wallet->available_balance, $amount, 8);
            $wallet->save();

            return $this->writeEntry($wallet, 'release', $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId);
        });
    }

    /** Settles a trade: takes coins out of the seller's HELD balance and credits the buyer's AVAILABLE balance, in one atomic step. */
    public function settleHeldToOwner(string $fromOwnerType, int $fromOwnerId, string $toOwnerType, int $toOwnerId, string $amount, string $reason, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null, string $actorType = 'system', ?int $actorId = null): array
    {
        $this->assertPositiveAmount($amount);

        return DB::transaction(function () use ($fromOwnerType, $fromOwnerId, $toOwnerType, $toOwnerId, $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId) {
            $outKey = $idempotencyKey . ':out';
            $inKey = $idempotencyKey . ':in';
            $existingOut = $this->existingEntry($outKey);
            $existingIn = $this->existingEntry($inKey);
            if ($existingOut && $existingIn) return ['out' => $existingOut, 'in' => $existingIn];

            // Lock both wallets in a fixed order (by owner_type then owner_id) so two trades
            // settling between the same pair of owners can never deadlock each other - the same
            // fix already applied to the balance-transfer race in Phase 0.5.
            $this->lockWalletsInOrder([[$fromOwnerType, $fromOwnerId], [$toOwnerType, $toOwnerId]]);

            $fromWallet = $this->wallet($fromOwnerType, $fromOwnerId)->fresh();
            if (bccomp((string) $fromWallet->held_balance, $amount, 8) < 0) {
                throw new InvalidArgumentException('Insufficient held coin balance to settle.');
            }
            $fromWallet->held_balance = bcsub((string) $fromWallet->held_balance, $amount, 8);
            $fromWallet->save();
            $outEntry = $this->writeEntry($fromWallet, 'transfer_out', $amount, $reason, $outKey, $referenceType, $referenceId, $actorType, $actorId);

            $toWallet = $this->wallet($toOwnerType, $toOwnerId)->fresh();
            $toWallet->available_balance = bcadd((string) $toWallet->available_balance, $amount, 8);
            $toWallet->save();
            $inEntry = $this->writeEntry($toWallet, 'transfer_in', $amount, $reason, $inKey, $referenceType, $referenceId, $actorType, $actorId);

            return ['out' => $outEntry, 'in' => $inEntry];
        });
    }

    /** Brings new coins into circulation: credits the owner, debits the platform wallet by the same amount (allowed negative). */
    public function mint(string $ownerType, int $ownerId, string $amount, string $reason, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null, string $actorType = 'system', ?int $actorId = null): array
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId) {
            $credit = $this->credit($ownerType, $ownerId, $amount, $reason, $idempotencyKey . ':mint_in', $referenceType, $referenceId, $actorType, $actorId);
            $debit = $this->debit('platform', self::PLATFORM_OWNER_ID, $amount, $reason, $idempotencyKey . ':mint_out', $referenceType, $referenceId, $actorType, $actorId);
            return ['credit' => $credit, 'platform_debit' => $debit];
        });
    }

    /** Removes coins from circulation: debits the owner, credits the platform wallet back by the same amount. */
    public function burn(string $ownerType, int $ownerId, string $amount, string $reason, string $idempotencyKey, ?string $referenceType = null, ?int $referenceId = null, string $actorType = 'system', ?int $actorId = null): array
    {
        return DB::transaction(function () use ($ownerType, $ownerId, $amount, $reason, $idempotencyKey, $referenceType, $referenceId, $actorType, $actorId) {
            $debit = $this->debit($ownerType, $ownerId, $amount, $reason, $idempotencyKey . ':burn_out', $referenceType, $referenceId, $actorType, $actorId);
            $credit = $this->credit('platform', self::PLATFORM_OWNER_ID, $amount, $reason, $idempotencyKey . ':burn_in', $referenceType, $referenceId, $actorType, $actorId);
            return ['debit' => $debit, 'platform_credit' => $credit];
        });
    }

    /**
     * Sums every non-platform wallet's available+held balance and confirms it is the exact
     * negation of the platform wallet's available_balance - the double-entry invariant that must
     * hold after every mint/burn if nothing has bypassed this service. Returns [ok, circulating,
     * platform_balance, difference].
     */
    public function reconcile(): array
    {
        $circulating = CoinWallet::where('owner_type', '!=', 'platform')
            ->get()
            ->reduce(fn ($sum, $wallet) => bcadd($sum, bcadd((string) $wallet->available_balance, (string) $wallet->held_balance, 8), 8), '0');

        $platform = (string) ($this->wallet('platform', self::PLATFORM_OWNER_ID)->available_balance);
        $difference = bcadd($circulating, $platform, 8);

        return [
            'ok' => bccomp($difference, '0', 8) === 0,
            'circulating' => $circulating,
            'platform_balance' => $platform,
            'difference' => $difference,
        ];
    }

    private function lockedWallet(string $ownerType, int $ownerId): CoinWallet
    {
        $wallet = $this->wallet($ownerType, $ownerId);
        return CoinWallet::whereKey($wallet->id)->lockForUpdate()->first();
    }

    private function lockWalletsInOrder(array $owners): void
    {
        $ids = array_map(fn ($pair) => $this->wallet($pair[0], $pair[1])->id, $owners);
        sort($ids);
        CoinWallet::whereIn('id', $ids)->lockForUpdate()->get();
    }

    private function existingEntry(string $idempotencyKey): ?CoinLedgerEntry
    {
        return CoinLedgerEntry::where('idempotency_key', $idempotencyKey)->first();
    }

    private function writeEntry(CoinWallet $wallet, string $type, string $amount, string $reason, string $idempotencyKey, ?string $referenceType, ?int $referenceId, string $actorType, ?int $actorId): CoinLedgerEntry
    {
        return CoinLedgerEntry::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'available_balance_after' => $wallet->available_balance,
            'held_balance_after' => $wallet->held_balance,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'idempotency_key' => $idempotencyKey,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
    }

    private function assertPositiveAmount(string $amount): void
    {
        if (bccomp($amount, '0', 8) <= 0) {
            throw new InvalidArgumentException('Coin amounts must be greater than zero.');
        }
    }
}
