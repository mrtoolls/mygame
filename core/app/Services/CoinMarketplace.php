<?php

namespace App\Services;

use App\Models\CoinListing;
use App\Models\CoinPenalty;
use App\Models\CoinTrade;
use App\Models\CoinTradeEvent;
use App\Models\SupportAgent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The P2P coin marketplace state machine. Core rule, enforced everywhere here: coins never move
 * without the receiving party's own confirmation or a supervisor's ruling - there is no code
 * path that releases held coins on a timer. A timeout always moves a trade to a human-reviewed
 * state (back to ON_SALE if no payment was ever claimed, or to DISPUTED if payment was claimed
 * but the seller never responded) and never auto-completes it.
 */
class CoinMarketplace
{
    public function __construct(private CoinLedger $ledger)
    {
    }

    // ----------------------------------------------------------------- Sell-to-agent: listings

    public function createListing(User $seller, string $amount): CoinListing
    {
        $this->assertWithinLimits($amount, 'coin_min_listing_amount', 'coin_max_listing_amount');
        $this->assertWholeCoins($amount);
        $this->assertOpenTradeLimit('user', $seller->id);

        return DB::transaction(function () use ($seller, $amount) {
            $listing = CoinListing::create([
                'seller_user_id' => $seller->id,
                'amount' => $amount,
                'status' => 'on_sale',
            ]);

            $this->ledger->hold('user', $seller->id, $amount, 'Coin listing #' . $listing->id, 'listing_hold:' . $listing->id, 'CoinListing', $listing->id, 'user', $seller->id);
            $this->logEvent(null, $listing->id, 'user', $seller->id, 'listing_created', null, ['amount' => $amount]);

            return $listing->fresh();
        });
    }

    /** Only while still ON_SALE - once an agent has claimed it, section "cancelBeforePayment" governs. */
    public function cancelListing(CoinListing $listing, string $actorType, int $actorId): CoinListing
    {
        return DB::transaction(function () use ($listing, $actorType, $actorId) {
            $locked = CoinListing::whereKey($listing->id)->lockForUpdate()->first();
            if ($locked->status !== 'on_sale') {
                throw new InvalidArgumentException('Only a listing that is still on sale can be cancelled directly.');
            }
            $locked->status = 'cancelled';
            $locked->save();

            $this->ledger->release('user', $locked->seller_user_id, (string) $locked->amount, 'Listing #' . $locked->id . ' cancelled', 'listing_release:' . $locked->id, 'CoinListing', $locked->id, $actorType, $actorId);
            $this->logEvent(null, $locked->id, $actorType, $actorId, 'listing_cancelled');

            return $locked;
        });
    }

    public function claimListing(CoinListing $listing, SupportAgent $agent): CoinTrade
    {
        $this->assertOpenTradeLimit('agent', $agent->id);

        return DB::transaction(function () use ($listing, $agent) {
            $locked = CoinListing::whereKey($listing->id)->lockForUpdate()->first();
            if ($locked->status !== 'on_sale') {
                throw new InvalidArgumentException('This listing is no longer available - another agent may already have claimed it.');
            }
            if ($locked->seller_user_id === null) {
                throw new InvalidArgumentException('Invalid listing.');
            }

            $locked->status = 'pending';
            $locked->claimed_by_agent_id = $agent->id;
            $locked->save();

            $trade = $this->openTrade($locked->id, 'sell_to_agent', 'user', $locked->seller_user_id, 'agent', $agent->id, (string) $locked->amount);
            $this->logEvent($trade->id, $locked->id, 'agent', $agent->id, 'listing_claimed');

            return $trade;
        });
    }

    // ----------------------------------------------------------------- Buy-from-agent (no listing)

    public function initiateBuyFromAgent(User $buyer, SupportAgent $agent, string $amount): CoinTrade
    {
        if (!$agent->is_selling_coins || !$agent->is_active) {
            throw new InvalidArgumentException('This agent is not currently selling coins.');
        }
        $this->assertWithinLimits($amount, 'coin_min_trade_amount', 'coin_max_trade_amount');
        $this->assertWholeCoins($amount);
        $this->assertOpenTradeLimit('user', $buyer->id);
        $this->assertOpenTradeLimit('agent', $agent->id);
        $this->assertAgentDailyLimit($agent->id, $amount);

        return DB::transaction(function () use ($buyer, $agent, $amount) {
            $this->ledger->hold('agent', $agent->id, $amount, 'Direct coin sale to user #' . $buyer->id, 'direct_hold:' . $buyer->id . ':' . now()->timestamp . ':' . bin2hex(random_bytes(4)), 'User', $buyer->id, 'agent', $agent->id);
            $trade = $this->openTrade(null, 'buy_from_agent', 'agent', $agent->id, 'user', $buyer->id, $amount);
            $this->logEvent($trade->id, null, 'user', $buyer->id, 'trade_initiated');

            return $trade;
        });
    }

    private function openTrade(?int $listingId, string $direction, string $sellerType, int $sellerId, string $buyerType, int $buyerId, string $amount): CoinTrade
    {
        $general = gs();
        $feePercent = (string) ($general->coin_fee_percent ?? '2');
        $feePayer = $general->coin_fee_payer ?? 'seller';
        $feeAmount = bcdiv(bcmul($amount, $feePercent, 8), '100', 8);

        return CoinTrade::create([
            'listing_id' => $listingId,
            'direction' => $direction,
            'coin_seller_type' => $sellerType,
            'coin_seller_id' => $sellerId,
            'coin_buyer_type' => $buyerType,
            'coin_buyer_id' => $buyerId,
            'amount' => $amount,
            'fee_amount' => $feeAmount,
            'fee_payer' => $feePayer,
            'status' => 'pending',
            'payment_deadline_at' => now()->addMinutes((int) ($general->coin_payment_window_minutes ?? 30)),
        ]);
    }

    // ----------------------------------------------------------------- Shared lifecycle

    /** Only the coin buyer (the one paying real money) can claim they've paid. */
    public function claimPayment(CoinTrade $trade, string $actorType, int $actorId, ?string $proofPath): CoinTrade
    {
        return DB::transaction(function () use ($trade, $actorType, $actorId, $proofPath) {
            $locked = CoinTrade::whereKey($trade->id)->lockForUpdate()->first();
            if ($locked->status !== 'pending') throw new InvalidArgumentException('This trade is not awaiting payment.');
            if ($locked->coin_buyer_type !== $actorType || (int) $locked->coin_buyer_id !== $actorId) {
                throw new InvalidArgumentException('Only the party paying can claim payment.');
            }

            $general = gs();
            $locked->status = 'paid_claimed';
            $locked->payment_claimed_at = now();
            $locked->confirmation_deadline_at = now()->addMinutes((int) ($general->coin_confirmation_window_minutes ?? 60));
            $locked->proof_attachment_path = $proofPath;
            $locked->save();

            $this->logEvent($locked->id, $locked->listing_id, $actorType, $actorId, 'payment_claimed');

            return $locked;
        });
    }

    /** Only the coin seller (the one being paid) can confirm - this is the only path that releases held coins, and it is never automatic. */
    public function confirmReceived(CoinTrade $trade, string $actorType, int $actorId): CoinTrade
    {
        return DB::transaction(function () use ($trade, $actorType, $actorId) {
            $locked = CoinTrade::whereKey($trade->id)->lockForUpdate()->first();
            if ($locked->status !== 'paid_claimed') throw new InvalidArgumentException('This trade has no payment claim to confirm.');
            if ($locked->coin_seller_type !== $actorType || (int) $locked->coin_seller_id !== $actorId) {
                throw new InvalidArgumentException('Only the party being paid can confirm receipt.');
            }

            $this->settle($locked);

            $locked->status = 'completed';
            $locked->completed_at = now();
            $locked->save();

            if ($locked->listing_id) {
                \App\Models\CoinListing::whereKey($locked->listing_id)->update(['status' => 'pending']);
            }

            $this->logEvent($locked->id, $locked->listing_id, $actorType, $actorId, 'trade_completed');

            return $locked;
        });
    }

    private function settle(CoinTrade $trade): void
    {
        $net = bcsub((string) $trade->amount, (string) $trade->fee_amount, 8);
        $key = 'trade_settle:' . $trade->id;

        if ($trade->fee_payer === 'seller') {
            // Fee comes out of the seller's held pool before the rest is transferred - burning
            // it (not crediting the platform's own wallet) keeps the ledger's double-entry
            // invariant trivially true: fee coins simply leave circulation.
            if (bccomp((string) $trade->fee_amount, '0', 8) > 0) {
                $this->ledger->release($trade->coin_seller_type, $trade->coin_seller_id, (string) $trade->fee_amount, 'Trade #' . $trade->id . ' fee', $key . ':fee_release');
                $this->ledger->burn($trade->coin_seller_type, $trade->coin_seller_id, (string) $trade->fee_amount, 'Trade #' . $trade->id . ' platform fee', $key . ':fee_burn', 'CoinTrade', $trade->id);
            }
            $this->ledger->settleHeldToOwner($trade->coin_seller_type, $trade->coin_seller_id, $trade->coin_buyer_type, $trade->coin_buyer_id, $net, 'Trade #' . $trade->id . ' settlement', $key . ':transfer', 'CoinTrade', $trade->id);
        } else {
            $this->ledger->settleHeldToOwner($trade->coin_seller_type, $trade->coin_seller_id, $trade->coin_buyer_type, $trade->coin_buyer_id, (string) $trade->amount, 'Trade #' . $trade->id . ' settlement', $key . ':transfer', 'CoinTrade', $trade->id);
            if (bccomp((string) $trade->fee_amount, '0', 8) > 0) {
                $this->ledger->burn($trade->coin_buyer_type, $trade->coin_buyer_id, (string) $trade->fee_amount, 'Trade #' . $trade->id . ' platform fee', $key . ':fee_burn', 'CoinTrade', $trade->id);
            }
        }
    }

    public function reportNotReceived(CoinTrade $trade, string $actorType, int $actorId, ?string $reason = null): CoinTrade
    {
        return $this->openDispute($trade, $actorType, $actorId, $reason);
    }

    public function openDispute(CoinTrade $trade, string $actorType, int $actorId, ?string $reason = null): CoinTrade
    {
        return DB::transaction(function () use ($trade, $actorType, $actorId, $reason) {
            $locked = CoinTrade::whereKey($trade->id)->lockForUpdate()->first();
            if (!in_array($locked->status, ['pending', 'paid_claimed'], true)) {
                throw new InvalidArgumentException('Only an open trade can be disputed.');
            }
            $locked->status = 'disputed';
            $locked->dispute_opened_at = now();
            $locked->dispute_opened_by_type = $actorType;
            $locked->dispute_opened_by_id = $actorId;
            $locked->save();

            $this->logEvent($locked->id, $locked->listing_id, $actorType, $actorId, 'dispute_opened', $reason);

            return $locked;
        });
    }

    /** The seller's "put it back on sale" button - only before a payment claim, or after a supervisor rules no payment was made. */
    public function reopenListing(CoinTrade $trade, string $actorType, int $actorId): CoinTrade
    {
        return DB::transaction(function () use ($trade, $actorType, $actorId) {
            $locked = CoinTrade::whereKey($trade->id)->lockForUpdate()->first();
            $allowedBeforeClaim = $locked->status === 'pending';
            $allowedAfterRuling = $locked->status === 'disputed' && $locked->no_payment_ruled_at !== null;
            if (!$allowedBeforeClaim && !$allowedAfterRuling) {
                throw new InvalidArgumentException('This trade can no longer be put back on sale - payment has been claimed and no ruling has released it.');
            }
            if ($locked->coin_seller_type !== $actorType || (int) $locked->coin_seller_id !== $actorId) {
                throw new InvalidArgumentException('Only the seller can put their own listing back on sale.');
            }

            $locked->status = 'cancelled';
            $locked->cancelled_at = now();
            $locked->cancelled_reason = 'Seller reopened the listing';
            $locked->save();

            if ($locked->listing_id) {
                \App\Models\CoinListing::whereKey($locked->listing_id)->update(['status' => 'on_sale', 'claimed_by_agent_id' => null]);
            } else {
                // buy_from_agent has no listing to reopen - just release the agent's held coins back.
                $this->ledger->release($locked->coin_seller_type, $locked->coin_seller_id, (string) $locked->amount, 'Trade #' . $locked->id . ' reopened', 'trade_release:' . $locked->id);
            }

            $this->logEvent($locked->id, $locked->listing_id, $actorType, $actorId, 'trade_reopened');

            return $locked;
        });
    }

    // ----------------------------------------------------------------- Timeouts (cron-driven, never auto-releases coins)

    public function sweepPaymentWindowExpiry(): int
    {
        $expired = CoinTrade::where('status', 'pending')->where('payment_deadline_at', '<', now())->get();
        $count = 0;
        foreach ($expired as $trade) {
            DB::transaction(function () use ($trade, &$count) {
                $locked = CoinTrade::whereKey($trade->id)->lockForUpdate()->first();
                if ($locked->status !== 'pending' || $locked->payment_deadline_at >= now()) return;

                $locked->status = 'expired';
                $locked->cancelled_at = now();
                $locked->save();

                if ($locked->listing_id) {
                    \App\Models\CoinListing::whereKey($locked->listing_id)->update(['status' => 'on_sale', 'claimed_by_agent_id' => null]);
                } else {
                    $this->ledger->release($locked->coin_seller_type, $locked->coin_seller_id, (string) $locked->amount, 'Trade #' . $locked->id . ' payment window expired', 'trade_release:' . $locked->id);
                }

                $this->logEvent($locked->id, $locked->listing_id, 'system', null, 'payment_window_expired');
                $count++;
            });
        }
        return $count;
    }

    public function sweepConfirmationWindowEscalation(): int
    {
        $overdue = CoinTrade::where('status', 'paid_claimed')->where('confirmation_deadline_at', '<', now())->get();
        $count = 0;
        foreach ($overdue as $trade) {
            DB::transaction(function () use ($trade, &$count) {
                $locked = CoinTrade::whereKey($trade->id)->lockForUpdate()->first();
                if ($locked->status !== 'paid_claimed' || $locked->confirmation_deadline_at >= now()) return;

                $locked->status = 'disputed';
                $locked->dispute_opened_at = now();
                $locked->dispute_opened_by_type = 'system';
                $locked->save();

                $this->logEvent($locked->id, $locked->listing_id, 'system', null, 'confirmation_window_escalated');
                $count++;
            });
        }
        return $count;
    }

    // ----------------------------------------------------------------- Supervisor resolution

    public function resolveDispute(CoinTrade $trade, string $decision, string $resolverType, int $resolverId, string $reason): CoinTrade
    {
        $this->assertSupervisor($resolverType, $resolverId);

        return DB::transaction(function () use ($trade, $decision, $resolverType, $resolverId, $reason) {
            $locked = CoinTrade::whereKey($trade->id)->lockForUpdate()->first();
            if ($locked->status !== 'disputed') throw new InvalidArgumentException('Only a disputed trade can be resolved this way.');

            if ($decision === 'release_to_buyer') {
                $this->settle($locked);
                $locked->status = 'completed';
                $locked->completed_at = now();
            } elseif ($decision === 'return_to_seller') {
                // A listing-backed trade's hold has been against the LISTING since it was
                // created, never re-held per trade attempt - reopening the listing is enough,
                // the coins were never released to begin with. Only a listing-less
                // buy_from_agent trade needs an explicit release (matches reopenListing()'s
                // identical branching for the seller's own "put back on sale" action).
                if ($locked->listing_id) {
                    \App\Models\CoinListing::whereKey($locked->listing_id)->update(['status' => 'on_sale', 'claimed_by_agent_id' => null]);
                } else {
                    $this->ledger->release($locked->coin_seller_type, $locked->coin_seller_id, (string) $locked->amount, 'Trade #' . $locked->id . ' returned to seller by supervisor ruling', 'trade_release:' . $locked->id);
                }
                $locked->status = 'cancelled';
                $locked->cancelled_at = now();
                $locked->no_payment_ruled_at = now();
            } else {
                throw new InvalidArgumentException('Unknown dispute decision.');
            }

            $locked->resolved_by_type = $resolverType;
            $locked->resolved_by_id = $resolverId;
            $locked->save();

            $this->logEvent($locked->id, $locked->listing_id, $resolverType, $resolverId, 'dispute_resolved:' . $decision, $reason);

            return $locked;
        });
    }

    public function issuePenalty(CoinTrade $trade, string $offenderType, int $offenderId, int $tier, string $reason, string $issuerType, int $issuerId, ?string $secondApproverType = null, ?int $secondApproverId = null): CoinPenalty
    {
        $this->assertSupervisor($issuerType, $issuerId);
        $general = gs();
        $threshold = (string) ($general->coin_penalty_second_approval_threshold ?? 500);
        $percent = (string) match ($tier) {
            1 => $general->coin_penalty_tier_1_percent ?? 10,
            2 => $general->coin_penalty_tier_2_percent ?? 25,
            3 => $general->coin_penalty_tier_3_percent ?? 50,
            default => throw new InvalidArgumentException('Penalty tier must be 1, 2 or 3.'),
        };
        $splitPercent = (string) ($general->coin_penalty_split_wronged_percent ?? 50);

        return DB::transaction(function () use ($trade, $offenderType, $offenderId, $tier, $percent, $splitPercent, $reason, $issuerType, $issuerId, $secondApproverType, $secondApproverId, $threshold, $general) {
            $wallet = $this->ledger->wallet($offenderType, $offenderId)->fresh();
            $maxPenalty = bcadd((string) $wallet->available_balance, (string) $wallet->held_balance, 8);
            $uncapped = bcdiv(bcmul((string) $trade->amount, $percent, 8), '100', 8);
            $coinAmount = bccomp($uncapped, $maxPenalty, 8) > 0 ? $maxPenalty : $uncapped;

            $isLarge = bccomp($coinAmount, $threshold, 8) > 0;
            if ($isLarge && !$general->allow_single_approver) {
                // Per the project's own rule: large penalties need two different ADMINS
                // specifically (not just a second supervisor) - a supervisor can issue it, but
                // sign-off above the threshold is reserved for admin accounts.
                if (!$secondApproverId || $secondApproverType !== 'admin') {
                    throw new InvalidArgumentException('A penalty of this size needs a second admin approver.');
                }
                if ($issuerType === 'admin' && $secondApproverId === $issuerId) {
                    throw new InvalidArgumentException('The second approver must be a different admin.');
                }
            }
            if ($secondApproverId !== null && $secondApproverType !== 'admin') $this->assertSupervisor($secondApproverType, $secondApproverId);

            $wrongedShare = bcdiv(bcmul($coinAmount, $splitPercent, 8), '100', 8);
            $platformShare = bcsub($coinAmount, $wrongedShare, 8);
            // Determine which side the offender is on FIRST (type+id together, not type alone) -
            // buyer and seller are very often the same owner_type ('user' vs 'user', or 'agent'
            // vs 'agent'), so comparing wrongedType back against coin_seller_type afterwards
            // (as an earlier version of this did) cannot tell the two apart and can wrongly
            // resolve the "wronged party" back to the offender themselves.
            $offenderIsSeller = $trade->coin_seller_type === $offenderType && (int) $trade->coin_seller_id === $offenderId;
            $wrongedType = $offenderIsSeller ? $trade->coin_buyer_type : $trade->coin_seller_type;
            $wrongedId = $offenderIsSeller ? $trade->coin_buyer_id : $trade->coin_seller_id;

            // Taking the penalty out of the offender is a burn (debit + a matching platform
            // credit), not a bare debit - the coins are leaving circulation entirely, same
            // treatment as the trade fee. The wronged party's share is then minted fresh for
            // them below; using debit() alone here (an earlier version of this did) would
            // shrink circulating supply without the platform wallet's negative balance moving
            // to match, breaking reconcile()'s invariant.
            if (bccomp((string) $wallet->available_balance, $coinAmount, 8) < 0) {
                $fromHeld = bcsub($coinAmount, (string) $wallet->available_balance, 8);
                if (bccomp((string) $wallet->available_balance, '0', 8) > 0) {
                    $this->ledger->burn($offenderType, $offenderId, (string) $wallet->available_balance, 'Penalty #trade-' . $trade->id, 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId . ':avail');
                }
                $this->ledger->release($offenderType, $offenderId, $fromHeld, 'Penalty release', 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId . ':held_release');
                $this->ledger->burn($offenderType, $offenderId, $fromHeld, 'Penalty #trade-' . $trade->id, 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId . ':held');
            } else {
                $this->ledger->burn($offenderType, $offenderId, $coinAmount, 'Penalty #trade-' . $trade->id, 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId);
            }

            if (bccomp($wrongedShare, '0', 8) > 0) {
                $this->ledger->mint($wrongedType, $wrongedId, $wrongedShare, 'Penalty compensation for trade #' . $trade->id, 'penalty_comp:' . $trade->id . ':' . $offenderType . ':' . $offenderId);
            }

            $penalty = CoinPenalty::create([
                'trade_id' => $trade->id,
                'offender_type' => $offenderType,
                'offender_id' => $offenderId,
                'tier' => $tier,
                'percent' => $percent,
                'coin_amount' => $coinAmount,
                'split_to_wronged_party' => $wrongedShare,
                'split_to_platform' => $platformShare,
                'reason' => $reason,
                'issued_by_type' => $issuerType,
                'issued_by_id' => $issuerId,
                'second_approver_type' => $secondApproverType,
                'second_approver_id' => $secondApproverId,
            ]);

            $this->logEvent($trade->id, $trade->listing_id, $issuerType, $issuerId, 'penalty_issued', $reason, ['tier' => $tier, 'amount' => $coinAmount]);

            return $penalty;
        });
    }

    // ----------------------------------------------------------------- Guards

    /** Supervisors are either an Admin (full override) or a SupportAgent with is_marketplace_supervisor - never a regular agent or user, and never is_moderator (that flag is unrelated, Arena content moderation). */
    private function assertSupervisor(string $type, int $id): void
    {
        if ($type === 'admin') return;
        if ($type === 'agent') {
            $agent = \App\Models\SupportAgent::find($id);
            if ($agent?->is_marketplace_supervisor) return;
        }
        throw new InvalidArgumentException('Only a marketplace supervisor or an admin can do this.');
    }

    private function assertWholeCoins(string $amount): void
    {
        if (bccomp($amount, bcadd(floor((float) $amount), '0', 8), 8) !== 0) {
            throw new InvalidArgumentException('Trade and listing amounts must be whole coins.');
        }
    }

    private function assertWithinLimits(string $amount, string $minKey, string $maxKey): void
    {
        $general = gs();
        $min = (string) ($general->$minKey ?? '0');
        $max = (string) ($general->$maxKey ?? '0');
        if (bccomp($amount, $min, 8) < 0 || ($max && bccomp($amount, $max, 8) > 0)) {
            throw new InvalidArgumentException("Amount must be between {$min} and {$max} coins.");
        }
    }

    private function assertOpenTradeLimit(string $ownerType, int $ownerId): void
    {
        $general = gs();
        $limitKey = $ownerType === 'agent' ? 'coin_max_open_trades_per_agent' : 'coin_max_open_trades_per_user';
        $limit = (int) ($general->$limitKey ?? 0);
        if ($limit <= 0) return;

        $open = CoinTrade::whereIn('status', ['pending', 'paid_claimed', 'disputed'])
            ->where(function ($query) use ($ownerType, $ownerId) {
                $query->where(['coin_seller_type' => $ownerType, 'coin_seller_id' => $ownerId])
                    ->orWhere(['coin_buyer_type' => $ownerType, 'coin_buyer_id' => $ownerId]);
            })->count();

        if ($open >= $limit) {
            throw new InvalidArgumentException('You have reached the maximum number of open trades.');
        }
    }

    private function assertAgentDailyLimit(int $agentId, string $amount): void
    {
        $general = gs();
        $limit = (string) ($general->coin_agent_daily_limit ?? '0');
        if (bccomp($limit, '0', 8) <= 0) return;

        $todayTotal = CoinTrade::where('coin_seller_type', 'agent')->where('coin_seller_id', $agentId)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', ['pending', 'paid_claimed', 'completed'])
            ->sum('amount');

        if (bccomp(bcadd((string) $todayTotal, $amount, 8), $limit, 8) > 0) {
            throw new InvalidArgumentException('This agent has reached their daily coin-selling limit.');
        }
    }

    private function logEvent(?int $tradeId, ?int $listingId, string $actorType, ?int $actorId, string $action, ?string $reason = null, ?array $metadata = null): void
    {
        if (!$tradeId) return; // listing-only events (e.g. initial creation) are covered by the ledger's own entry
        CoinTradeEvent::create([
            'trade_id' => $tradeId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
