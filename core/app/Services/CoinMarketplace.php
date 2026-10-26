<?php

namespace App\Services;

use App\Models\CoinListing;
use App\Models\CoinPenalty;
use App\Models\CoinTrade;
use App\Models\CoinTradeEvent;
use App\Models\CoinRate;
use App\Models\CoinVendor;
use App\Models\CoinVendorAudit;
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
        app(CoinMarketplaceCountryAccess::class)->assertEnabled($seller);
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

    /** Vendor users buy player listings from their existing user coin wallet. */
    public function claimListingForVendor(CoinListing $listing, CoinVendor $vendor): CoinTrade
    {
        $vendorService = app(CoinVendorService::class);
        $vendorService->assertVendorCanTrade($vendor);
        if ($vendor->status !== 'active') throw new InvalidArgumentException('This vendor account is not active.');
        if ((int) $listing->seller_user_id === (int) $vendor->user_id) throw new InvalidArgumentException('You cannot trade with your own account.');
        if (in_array((int) $listing->seller_user_id, array_map('intval', $vendor->linked_user_ids ?: []), true)) throw new InvalidArgumentException('This account is linked to your vendor profile and cannot trade with you.');

        return DB::transaction(function () use ($listing, $vendor) {
            $lockedVendor = CoinVendor::whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            app(CoinVendorService::class)->assertVendorCanTrade($lockedVendor);
            if ($lockedVendor->status !== 'active') throw new InvalidArgumentException('This vendor account is not active.');
            if (in_array((int) $listing->seller_user_id, array_map('intval', $lockedVendor->linked_user_ids ?: []), true)) throw new InvalidArgumentException('This account is linked to your vendor profile and cannot trade with you.');
            $locked = CoinListing::whereKey($listing->id)->lockForUpdate()->first();
            if (!$locked) throw new InvalidArgumentException('This listing is no longer available.');
            if ($locked->status !== 'on_sale') {
                if ($locked->status === 'pending' && (int) $locked->claimed_by_user_id === (int) $lockedVendor->user_id) {
                    $existingTrade = CoinTrade::where('listing_id', $locked->id)->where('coin_buyer_type', 'user')->where('coin_buyer_id', $lockedVendor->user_id)->whereIn('status', ['pending', 'paid_claimed', 'disputed'])->first();
                    if ($existingTrade) return $existingTrade;
                }
                throw new InvalidArgumentException('This listing is no longer available.');
            }
            $this->assertVendorLimit($lockedVendor, (string) $locked->amount);
            $this->assertOpenTradeLimit('user', (int) $lockedVendor->user_id);
            if ((int) $locked->seller_user_id === (int) $lockedVendor->user_id) throw new InvalidArgumentException('You cannot buy your own listing.');
            $seller = User::with('currency')->findOrFail($locked->seller_user_id);
            $currency = $seller->currency;
            $rate = $currency ? CoinRate::where('currency_id', $currency->id)->first() : null;
            if (!$rate?->sell_price) throw new InvalidArgumentException('The listing seller has no active coin sell reference.');

            $locked->status = 'pending';
            $locked->claimed_by_user_id = $lockedVendor->user_id;
            $locked->save();
            $trade = $this->openTrade($locked->id, 'sell_to_agent', 'user', $locked->seller_user_id, 'user', $lockedVendor->user_id, (string) $locked->amount, [
                'price_per_coin' => $rate->sell_price,
                'fiat_currency' => $currency->code,
                'fiat_total' => bcmul((string) $locked->amount, (string) $rate->sell_price, 8),
            ]);
            $this->logEvent($trade->id, $locked->id, 'user', $lockedVendor->user_id, 'vendor_listing_claimed', null, ['vendor_id' => $lockedVendor->id]);
            $this->notifyVendorParty($locked->seller_user_id, 'A vendor claimed your listing', 'The buyer is a verified Coin Vendor. Open trade #' . $trade->id . ' to arrange payment.');
            return $trade;
        });
    }

    // ----------------------------------------------------------------- Buy-from-agent (no listing)

    public function initiateBuyFromAgent(User $buyer, SupportAgent $agent, string $amount): CoinTrade
    {
        app(CoinMarketplaceCountryAccess::class)->assertEnabled($buyer);
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

    /** Buyer-facing sale from an approved vendor, settled through the same escrow/ledger flow. */
    public function initiateBuyFromVendor(User $buyer, CoinVendor $vendor, string $amount): CoinTrade
    {
        app(CoinMarketplaceCountryAccess::class)->assertEnabled($buyer);
        app(CoinVendorService::class)->assertVendorCanTrade($vendor);
        if ((int) $buyer->id === (int) $vendor->user_id) throw new InvalidArgumentException('You cannot trade with your own account.');
        if (in_array((int) $buyer->id, array_map('intval', $vendor->linked_user_ids ?: []), true)) throw new InvalidArgumentException('This account is linked to the vendor and cannot trade with them.');
        if (!$vendor->is_selling || !$vendor->sell_rate) throw new InvalidArgumentException('This vendor is not currently selling coins.');
        $this->assertWithinLimits($amount, 'coin_min_trade_amount', 'coin_max_trade_amount');
        $this->assertWholeCoins($amount);
        if (bccomp($amount, (string) $vendor->per_trade_limit, 8) > 0) throw new InvalidArgumentException('This amount is above the vendor\'s per-trade limit.');
        $vendorCurrency = $vendor->currency;
        if (!$vendorCurrency) throw new InvalidArgumentException('The vendor has no active settlement currency.');
        $buyerCurrency = $buyer->currency ?: $vendorCurrency;
        $buyerRate = (float) ($buyerCurrency->rate_to_usd ?? 0);
        $vendorRate = (float) ($vendorCurrency->rate_to_usd ?? 0);
        if ($buyerRate <= 0 || $vendorRate <= 0) throw new InvalidArgumentException('A current exchange rate is unavailable for one of the trade currencies.');
        $localRate = bcmul((string) $vendor->sell_rate, bcdiv((string) $vendorRate, (string) $buyerRate, 12), 8);
        $fiatTotal = bcmul($amount, $localRate, 8);
        $feeSetting = gs('coin_vendor_fee_percent');
        $fee = $feeSetting === null || $feeSetting === '' ? null : (string) $feeSetting;

        return DB::transaction(function () use ($buyer, $vendor, $amount, $buyerCurrency, $fee) {
            // Serialize new commitments for this vendor so simultaneous buyers cannot bypass
            // their active flag, spendable balance, or per-trade/daily limits.
            $lockedVendor = CoinVendor::whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            app(CoinVendorService::class)->assertVendorCanTrade($lockedVendor);
            if (!$lockedVendor->is_selling || !$lockedVendor->sell_rate) throw new InvalidArgumentException('This vendor is not currently selling coins.');
            if ((int) $buyer->id === (int) $lockedVendor->user_id) throw new InvalidArgumentException('You cannot trade with your own account.');
            if (in_array((int) $buyer->id, array_map('intval', $lockedVendor->linked_user_ids ?: []), true)) throw new InvalidArgumentException('This account is linked to the vendor and cannot trade with them.');
            if (bccomp($amount, (string) $lockedVendor->per_trade_limit, 8) > 0) throw new InvalidArgumentException('This amount is above the vendor\'s per-trade limit.');
            $existingTrade = CoinTrade::whereNull('listing_id')
                ->where('coin_seller_type', 'user')->where('coin_seller_id', $lockedVendor->user_id)
                ->where('coin_buyer_type', 'user')->where('coin_buyer_id', $buyer->id)
                ->where('amount', $amount)->whereIn('status', ['pending', 'paid_claimed', 'disputed'])->latest('id')->first();
            if ($existingTrade) return $existingTrade;
            $this->assertOpenTradeLimit('user', $buyer->id);
            $this->assertOpenTradeLimit('user', $lockedVendor->user_id);
            $this->assertVendorLimit($lockedVendor, $amount);
            $lockedVendorCurrency = $lockedVendor->currency;
            if (!$lockedVendorCurrency) throw new InvalidArgumentException('The vendor has no active settlement currency.');
            $buyerRate = (float) ($buyerCurrency->rate_to_usd ?? 0);
            $vendorRate = (float) ($lockedVendorCurrency->rate_to_usd ?? 0);
            if ($buyerRate <= 0 || $vendorRate <= 0) throw new InvalidArgumentException('A current exchange rate is unavailable for one of the trade currencies.');
            $lockedLocalRate = bcmul((string) $lockedVendor->sell_rate, bcdiv((string) $vendorRate, (string) $buyerRate, 12), 8);
            $lockedFiatTotal = bcmul($amount, $lockedLocalRate, 8);
            $this->ledger->hold('user', $lockedVendor->user_id, $amount, 'Vendor coin sale to user #' . $buyer->id, 'vendor_direct_hold:' . $lockedVendor->id . ':' . $buyer->id . ':' . bin2hex(random_bytes(8)), 'User', $buyer->id, 'user', $lockedVendor->user_id);
            $trade = $this->openTrade(null, 'buy_from_agent', 'user', $lockedVendor->user_id, 'user', $buyer->id, $amount, [
                'price_per_coin' => $lockedLocalRate,
                'fiat_currency' => $buyerCurrency->code,
                'fiat_total' => $lockedFiatTotal,
            ], $fee);
            $this->logEvent($trade->id, null, 'user', $buyer->id, 'vendor_trade_initiated', null, ['vendor_id' => $lockedVendor->id, 'rate' => $lockedLocalRate, 'currency' => $buyerCurrency->code]);
            $this->notifyVendorParty((int) $lockedVendor->user_id, 'New coin sale request', 'A buyer opened trade #' . $trade->id . '. Open the trade to share payment details.');
            $this->notifyVendorParty((int) $buyer->id, 'Vendor trade started', 'Trade #' . $trade->id . ' is open. Use its chat to arrange payment.');
            return $trade;
        });
    }

    private function openTrade(?int $listingId, string $direction, string $sellerType, int $sellerId, string $buyerType, int $buyerId, string $amount, array $priceSnapshot = [], ?string $feePercentOverride = null): CoinTrade
    {
        $general = gs();
        $feePercent = $feePercentOverride ?? (string) ($general->coin_fee_percent ?? '2');
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
            'price_per_coin' => $priceSnapshot['price_per_coin'] ?? null,
            'fiat_currency' => $priceSnapshot['fiat_currency'] ?? null,
            'fiat_total' => $priceSnapshot['fiat_total'] ?? null,
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
            $counterparty = $locked->coin_buyer_type === $actorType && (int) $locked->coin_buyer_id === $actorId
                ? [$locked->coin_seller_type, (int) $locked->coin_seller_id]
                : [$locked->coin_buyer_type, (int) $locked->coin_buyer_id];
            $this->notifyVendorTradeParty($counterparty[0], $counterparty[1], 'Payment claimed for trade #' . $locked->id, 'Your trade partner marked payment as sent. Check the payment and respond in the trade.');

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
            $this->recordVendorCompletion($locked);
            $counterparty = $locked->coin_buyer_type === $actorType && (int) $locked->coin_buyer_id === $actorId
                ? [$locked->coin_seller_type, (int) $locked->coin_seller_id]
                : [$locked->coin_buyer_type, (int) $locked->coin_buyer_id];
            $this->notifyVendorTradeParty($counterparty[0], $counterparty[1], 'Trade #' . $locked->id . ' completed', 'The other party confirmed receipt and the escrowed coins were settled.');

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
            $counterparty = $locked->coin_buyer_type === $actorType && (int) $locked->coin_buyer_id === $actorId
                ? [$locked->coin_seller_type, (int) $locked->coin_seller_id]
                : [$locked->coin_buyer_type, (int) $locked->coin_buyer_id];
            $this->notifyVendorTradeParty($counterparty[0], $counterparty[1], 'Trade #' . $locked->id . ' needs review', 'A dispute was opened. The trade is held for supervisor review.');

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
                $this->reopenListingClaim($locked->listing_id);
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
                    $this->reopenListingClaim($locked->listing_id);
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
                    $this->reopenListingClaim($locked->listing_id);
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
            if ($decision === 'release_to_buyer') $this->recordVendorCompletion($locked);
            $this->notifyVendorTradeParty($locked->coin_seller_type, (int) $locked->coin_seller_id, 'Trade #' . $locked->id . ' dispute resolved', 'The supervisor decided: ' . str_replace('_', ' ', $decision) . '.');
            $this->notifyVendorTradeParty($locked->coin_buyer_type, (int) $locked->coin_buyer_id, 'Trade #' . $locked->id . ' dispute resolved', 'The supervisor decided: ' . str_replace('_', ' ', $decision) . '.');

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
            $trade = CoinTrade::whereKey($trade->id)->lockForUpdate()->firstOrFail();
            $isSeller = $trade->coin_seller_type === $offenderType && (int) $trade->coin_seller_id === $offenderId;
            $isBuyer = $trade->coin_buyer_type === $offenderType && (int) $trade->coin_buyer_id === $offenderId;
            if (!$isSeller && !$isBuyer) throw new InvalidArgumentException('A penalty offender must be one of the trade participants.');
            $existingPenalty = CoinPenalty::where('trade_id', $trade->id)->where('offender_type', $offenderType)->where('offender_id', $offenderId)->first();
            if ($existingPenalty) return $existingPenalty;
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

            // Vendor collateral is consumed before spendable and trade-held funds.
            $remainingCoinAmount = $coinAmount;
            if ($offenderType === 'user' && \Illuminate\Support\Facades\Schema::hasColumn('coin_wallets', 'vendor_bond_balance')) {
                $bondBalance = (string) ($this->ledger->wallet('user', $offenderId)->fresh()->vendor_bond_balance ?? '0');
                $bondTarget = bccomp($bondBalance, $remainingCoinAmount, 8) < 0 ? $bondBalance : $remainingCoinAmount;
                if (bccomp($bondTarget, '0', 8) > 0) {
                    $bondDeducted = $this->ledger->deductVendorBond($offenderId, $bondTarget, 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId . ':bond', 'Penalty #trade-' . $trade->id . ' deducted from vendor bond', $issuerType, $issuerId);
                    $remainingCoinAmount = bcsub($remainingCoinAmount, $bondDeducted, 8);
                    $vendorBond = CoinVendor::where('user_id', $offenderId)->lockForUpdate()->first();
                    if ($vendorBond && bccomp($bondDeducted, '0', 8) > 0) {
                        $vendorBond->bond_amount = bcsub((string) $vendorBond->bond_amount, $bondDeducted, 8);
                        $vendorBond->save();
                        CoinVendorAudit::record('vendor_bond_deducted', $vendorBond->application_id, $vendorBond->id, $issuerType === 'admin' ? $issuerId : null, null, $bondDeducted . ' coins deducted for penalty on trade #' . $trade->id . '.');
                        if (\Illuminate\Support\Facades\Schema::hasTable('admin_notifications')) {
                            $notice = new \App\Models\AdminNotification();
                            $notice->user_id = 0;
                            $notice->title = 'Vendor security bond deducted — vendor #' . $vendorBond->id . ': ' . $bondDeducted . ' coins for trade #' . $trade->id;
                            $notice->click_url = urlPath('admin.coin.vendors.index');
                            $notice->save();
                        }
                        $this->notifyVendorParty($offenderId, 'Vendor security bond reduced', $bondDeducted . ' coins were deducted from your vendor bond for a marketplace penalty.');
                    }
                }
            }

            // Taking the penalty out of the offender is a burn (debit + a matching platform
            // credit), not a bare debit - the coins are leaving circulation entirely, same
            // treatment as the trade fee. The wronged party's share is then minted fresh for
            // them below; using debit() alone here (an earlier version of this did) would
            // shrink circulating supply without the platform wallet's negative balance moving
            // to match, breaking reconcile()'s invariant.
            $wallet = $this->ledger->wallet($offenderType, $offenderId)->fresh();
            if (bccomp((string) $wallet->available_balance, $remainingCoinAmount, 8) < 0) {
                $fromHeld = bcsub($remainingCoinAmount, (string) $wallet->available_balance, 8);
                if (bccomp((string) $wallet->available_balance, '0', 8) > 0) {
                    $this->ledger->burn($offenderType, $offenderId, (string) $wallet->available_balance, 'Penalty #trade-' . $trade->id, 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId . ':avail');
                }
                $this->ledger->release($offenderType, $offenderId, $fromHeld, 'Penalty release', 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId . ':held_release');
                $this->ledger->burn($offenderType, $offenderId, $fromHeld, 'Penalty #trade-' . $trade->id, 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId . ':held');
            } else {
                if (bccomp($remainingCoinAmount, '0', 8) > 0) $this->ledger->burn($offenderType, $offenderId, $remainingCoinAmount, 'Penalty #trade-' . $trade->id, 'penalty:' . $trade->id . ':' . $offenderType . ':' . $offenderId);
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
            if ($offenderType === 'user' && \Illuminate\Support\Facades\Schema::hasTable('coin_vendors')) {
                $vendor = CoinVendor::where('user_id', $offenderId)->first();
                if ($vendor) {
                    $vendor->penalty_count++;
                    $vendor->reliability_score = max(0, (float) $vendor->reliability_score - ($tier * 10));
                    $suspendAt = (int) (gs('coin_vendor_auto_suspend_penalties') ?? 3);
                    if ($vendor->penalty_count >= $suspendAt) {
                        $vendor->status = 'suspended';
                        $vendor->is_selling = false;
                        $vendor->status_reason = 'Automatically suspended after ' . $vendor->penalty_count . ' marketplace penalties.';
                        $vendor->save();
                        \App\Models\CoinVendorAudit::record('vendor_auto_suspended', $vendor->application_id, $vendor->id, null, null, $vendor->status_reason);
                        $this->notifyVendorParty($offenderId, 'Coin Vendor account suspended', $vendor->status_reason . ' Existing trades remain open for resolution.');
                    } else {
                        $vendor->save();
                    }
                }
            }

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
        if ($ownerType === 'user' && \Illuminate\Support\Facades\Schema::hasTable('coin_vendors')) {
            $vendor = CoinVendor::where('user_id', $ownerId)->where('status', 'active')->first();
            if ($vendor) $limit = min($limit ?: (int) (gs('coin_vendor_max_open_trades') ?? 5), (int) (gs('coin_vendor_max_open_trades') ?? 5));
        }
        if ($limit <= 0) return;

        $open = CoinTrade::whereIn('status', ['pending', 'paid_claimed', 'disputed'])
            ->where(function ($query) use ($ownerType, $ownerId) {
                $query->where(['coin_seller_type' => $ownerType, 'coin_seller_id' => $ownerId])
                    ->orWhere(['coin_buyer_type' => $ownerType, 'coin_buyer_id' => $ownerId]);
            })->count();

        if ($open >= $limit) {
            throw new InvalidArgumentException('You have reached the maximum number of open trades (' . $open . '/' . $limit . ').');
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

    private function assertVendorLimit(CoinVendor $vendor, string $amount): void
    {
        if (bccomp($amount, (string) $vendor->per_trade_limit, 8) > 0) {
            throw new InvalidArgumentException('This amount is above the vendor\'s per-trade limit.');
        }
        $dailyLimit = (string) $vendor->daily_limit;
        if (bccomp($dailyLimit, '0', 8) <= 0) return;
        $used = CoinTrade::where('created_at', '>=', now()->startOfDay())
            ->whereIn('status', ['pending', 'paid_claimed', 'completed', 'disputed'])
            ->where(function ($query) use ($vendor) {
                $query->where(fn ($side) => $side->where('coin_seller_type', 'user')->where('coin_seller_id', $vendor->user_id))
                    ->orWhere(fn ($side) => $side->where('coin_buyer_type', 'user')->where('coin_buyer_id', $vendor->user_id));
            })->sum('amount');
        if (bccomp(bcadd((string) $used, $amount, 8), $dailyLimit, 8) > 0) {
            throw new InvalidArgumentException('This vendor has reached the daily coin trade limit.');
        }
    }

    private function recordVendorCompletion(CoinTrade $trade): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('coin_vendors')) return;
        $partyIds = [];
        foreach ([[$trade->coin_seller_type, $trade->coin_seller_id], [$trade->coin_buyer_type, $trade->coin_buyer_id]] as [$type, $id]) {
            if ($type === 'user' && $id) $partyIds[] = (int) $id;
        }
        foreach (array_unique($partyIds) as $userId) {
            $vendor = CoinVendor::where('user_id', $userId)->whereIn('status', ['active', 'suspended'])->lockForUpdate()->first();
            if (!$vendor) continue;
            $prior = (int) $vendor->completed_trades;
            $vendor->completed_trades = $prior + 1;
            $vendor->reliability_score = min(100, max(50, (float) $vendor->reliability_score + 0.5));
            if ($trade->coin_seller_type === 'user' && (int) $trade->coin_seller_id === $userId) {
                $minutes = max(0, (int) $trade->created_at->diffInMinutes($trade->completed_at ?? now()));
                $vendor->average_release_minutes = $prior === 0 ? $minutes : (int) round((((int) $vendor->average_release_minutes * $prior) + $minutes) / ($prior + 1));
            }
            $baseTrade = (float) (gs('coin_vendor_new_per_trade_limit') ?? 1000);
            $baseDaily = (float) (gs('coin_vendor_new_daily_limit') ?? 5000);
            if ($vendor->reliability_score >= 80) {
                $multiplier = min(5, 1 + intdiv($vendor->completed_trades, 10));
                $vendor->per_trade_limit = max((float) $vendor->per_trade_limit, $baseTrade * $multiplier);
                $vendor->daily_limit = max((float) $vendor->daily_limit, $baseDaily * $multiplier);
            }
            $vendor->save();
        }
    }

    private function reopenListingClaim(int $listingId): void
    {
        $values = ['status' => 'on_sale', 'claimed_by_agent_id' => null];
        if (\Illuminate\Support\Facades\Schema::hasColumn('coin_listings', 'claimed_by_user_id')) {
            $values['claimed_by_user_id'] = null;
        }
        \App\Models\CoinListing::whereKey($listingId)->update($values);
    }

    private function notifyVendorTradeParty(string $type, int $id, string $subject, string $message): void
    {
        if ($type !== 'user' || !\Illuminate\Support\Facades\Schema::hasTable('coin_vendors') || !CoinVendor::where('user_id', $id)->exists()) return;
        $this->notifyVendorParty($id, $subject, $message);
    }

    private function notifyVendorParty(int $userId, string $subject, string $message): void
    {
        try {
            $user = User::find($userId);
            if ($user) notify($user, 'DEFAULT', ['subject' => $subject, 'message' => $message]);
        } catch (\Throwable $exception) {
            report($exception);
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
