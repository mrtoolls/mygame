<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CoinListing;
use App\Models\CoinTrade;
use App\Models\CoinRate;
use App\Models\CoinVendor;
use App\Models\SupportAgent;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
use App\Support\WinxPayDirectory;
use App\Models\UserPresence;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CoinMarketplaceController extends Controller
{
    public function index()
    {
        $pageTitle = 'Coin Marketplace';
        $user = auth()->user();
        $wallet = app(CoinLedger::class)->wallet('user', $user->id);
        $directory = app(WinxPayDirectory::class);
        $agents = $directory->agentsForBuyer($user);
        $vendors = $directory->forBuyer($user);
        $minimumTradeAmount = (int) (gs('coin_min_trade_amount') ?? 10);
        $maximumTradeAmount = (int) (gs('coin_max_trade_amount') ?? 100000);
        $listings = CoinListing::with('seller.currency')
            ->where('status', 'on_sale')->where('seller_user_id', '!=', $user->id)
            ->whereHas('seller', fn ($query) => $query->where('status', 1)->where('coin_vendor_flagged', false))
            ->whereBetween('amount', [$minimumTradeAmount, $maximumTradeAmount])
            ->latest()->limit(24)->get();
        $listingRates = CoinRate::whereIn('currency_id', $listings->pluck('seller.currency_id')->filter()->unique())
            ->get()->keyBy('currency_id');
        $sellerVendorProfiles = CoinVendor::whereIn('user_id', $listings->pluck('seller_user_id'))
            ->get()->keyBy('user_id');
        $listings = $listings->filter(fn (CoinListing $listing) => $listing->seller
            && $listing->seller->currency
            && $listing->seller->currency_id
            && $listingRates->get($listing->seller->currency_id)?->sell_price
            && (!$sellerVendorProfiles->has($listing->seller_user_id) || $sellerVendorProfiles->get($listing->seller_user_id)->status === 'active'));
        $listingVendorIds = $listings->filter(fn (CoinListing $listing) => $sellerVendorProfiles->has($listing->seller_user_id))
            ->pluck('seller_user_id')->map(fn ($id) => (int) $id)->all();

        // Prepare one comparable, buyer-currency offer list for the marketplace UI.
        $buyerCurrency = $user->currency;
        $buyerUsdRate = (float) ($buyerCurrency?->rate_to_usd ?? 0);
        $marketplaceOffers = collect();
        foreach ($vendors as $vendor) {
            $marketplaceOffers->push([
                'key' => 'vendor-' . $vendor['id'], 'kind' => 'vendor', 'id' => $vendor['id'],
                'name' => $vendor['name'], 'avatar' => $vendor['avatar_url'], 'role' => 'vendor',
                'verified' => (bool) $vendor['verified'], 'online' => (bool) $vendor['online'],
                'amount' => (float) $vendor['available_coins'], 'price' => (float) $vendor['rate_per_1000'],
                'display_price' => (float) $vendor['rate_per_1000'], 'display_currency' => $buyerCurrency?->symbol ?: $vendor['currency_code'],
                'trade_limit' => (float) $vendor['per_trade_limit'],
                'currency' => $buyerCurrency?->code, 'currency_symbol' => $buyerCurrency?->symbol,
                'trades' => (int) $vendor['completed_trades'], 'release_minutes' => (int) $vendor['average_release_minutes'],
                'reliability' => (float) $vendor['reliability_score'], 'created' => now()->timestamp,
                'buy_url' => route('user.coin-vendor.buy'), 'buy_mode' => 'vendor',
            ]);
        }
        $agentIds = collect($agents)->pluck('id')->unique()->values();
        $agentTradeStats = $agentIds->isEmpty() ? collect() : CoinTrade::where('coin_seller_type', 'agent')
            ->where('status', 'completed')->whereIn('coin_seller_id', $agentIds)
            ->selectRaw('coin_seller_id, COUNT(*) as completed_count')->groupBy('coin_seller_id')->get()->keyBy('coin_seller_id');
        foreach ($agents as $agent) {
            $marketplaceOffers->push([
                'key' => 'agent-' . $agent['id'], 'kind' => 'agent', 'id' => $agent['id'],
                'name' => $agent['name'], 'avatar' => $agent['avatar_url'], 'role' => 'agent',
                'verified' => (bool) $agent['verified'], 'online' => (bool) $agent['online'],
                'amount' => (float) $agent['available_coins'], 'price' => (float) $agent['rate_per_1000'],
                'display_price' => (float) $agent['rate_per_1000'], 'display_currency' => $buyerCurrency?->symbol ?: $agent['currency_code'],
                'trade_limit' => (float) $agent['per_trade_limit'],
                'currency' => $agent['currency_code'], 'currency_symbol' => $buyerCurrency?->symbol,
                'trades' => (int) ($agentTradeStats->get($agent['id'])?->completed_count ?? 0), 'release_minutes' => null,
                'reliability' => (float) $agent['reliability_score'], 'created' => now()->timestamp,
                'buy_url' => route('user.coin.buy'), 'buy_mode' => 'agent',
            ]);
        }
        $memberIds = $listings->pluck('seller_user_id')->unique()->values();
        $memberTradeStats = CoinTrade::where('coin_seller_type', 'user')->where('status', 'completed')
            ->whereIn('coin_seller_id', $memberIds)->selectRaw('coin_seller_id, COUNT(*) as completed_count')
            ->groupBy('coin_seller_id')->get()->keyBy('coin_seller_id');
        try {
            $onlineMembers = UserPresence::whereIn('user_id', $memberIds)->where('last_seen_at', '>=', now()->subMinutes(5))->pluck('user_id')->flip();
        } catch (\Throwable) {
            $onlineMembers = collect();
        }
        foreach ($listings as $listing) {
            $seller = $listing->seller;
            $listingRate = $seller ? $listingRates->get($seller->currency_id) : null;
            $sellerUsdRate = (float) ($seller?->currency?->rate_to_usd ?? 0);
            $pricePerThousand = $listingRate?->sell_price && $sellerUsdRate > 0 && $buyerUsdRate > 0
                ? (float) $listingRate->sell_price * $sellerUsdRate / $buyerUsdRate * 1000 : 0;
            if (!$seller || $pricePerThousand <= 0) continue;
            $isVendor = in_array((int) $seller->id, $listingVendorIds, true);
            $marketplaceOffers->push([
                'key' => 'listing-' . $listing->id, 'kind' => 'listing', 'id' => $listing->id,
                'name' => $seller->username, 'avatar' => $seller->avatarUrl(), 'role' => $isVendor ? 'vendor' : 'member',
                'verified' => $isVendor, 'online' => $onlineMembers->has($seller->id),
                'amount' => (float) $listing->amount, 'price' => $pricePerThousand,
                'display_price' => (float) $listingRate->sell_price * 1000,
                'display_currency' => $seller->currency?->symbol ?: $seller->currency?->code,
                'trade_limit' => (float) $listing->amount,
                'currency' => $buyerCurrency?->code, 'currency_symbol' => $buyerCurrency?->symbol,
                'trades' => (int) ($memberTradeStats->get($seller->id)?->completed_count ?? 0), 'release_minutes' => null,
                'reliability' => null, 'created' => $listing->created_at?->timestamp ?? now()->timestamp,
                'buy_url' => route('user.coin.listings.buy', $listing->id), 'buy_mode' => 'listing',
                'listing_id' => $listing->id,
            ]);
        }
        $maxOfferPrice = (float) ($marketplaceOffers->max('price') ?? 0);
        $myListings = CoinListing::where('seller_user_id', $user->id)->whereIn('status', ['on_sale', 'pending'])->latest()->get();
        $myTrades = CoinTrade::where(function ($query) use ($user) {
            $query->where(['coin_seller_type' => 'user', 'coin_seller_id' => $user->id])
                ->orWhere(['coin_buyer_type' => 'user', 'coin_buyer_id' => $user->id]);
        })->latest()->paginate(getPaginate());

        return view('Template::user.coin.marketplace', compact('pageTitle', 'user', 'wallet', 'agents', 'vendors', 'minimumTradeAmount', 'maximumTradeAmount', 'listings', 'listingVendorIds', 'listingRates', 'myListings', 'myTrades', 'marketplaceOffers', 'maxOfferPrice'));
    }

    public function listingStore(Request $request)
    {
        $this->assertCoinMode();
        $data = $request->validate(['amount' => 'required|integer|gt:0']);

        try {
            app(CoinMarketplace::class)->createListing(auth()->user(), (string) $data['amount']);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Your coins are listed for sale. Agents can now see and buy this listing.']]);
    }

    public function listingCancel(int $id)
    {
        $listing = CoinListing::where('seller_user_id', auth()->id())->findOrFail($id);
        try {
            app(CoinMarketplace::class)->cancelListing($listing, 'user', auth()->id());
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Listing cancelled and your coins were released back to your available balance.']]);
    }

    public function listingBuy(int $id)
    {
        $this->assertCoinMode();
        $buyer = auth()->user();
        $listing = CoinListing::findOrFail($id);
        $vendorProfile = CoinVendor::where('user_id', $buyer->id)->whereIn('status', ['active', 'suspended'])->first();

        try {
            if ($vendorProfile && $vendorProfile->status === 'suspended') {
                throw new InvalidArgumentException('Your Coin Vendor account cannot start marketplace trades right now.');
            }
            $trade = $vendorProfile
                ? app(CoinMarketplace::class)->claimListingForVendor($listing, $vendorProfile)
                : app(CoinMarketplace::class)->claimListingForUser($listing, $buyer);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return to_route('user.coin.trade', $trade->id)->withNotify([['success', 'Listing claimed. Open the trade chat to arrange payment.']]);
    }

    public function buyFromAgent(Request $request)
    {
        $this->assertCoinMode();
        $data = $request->validate(['support_agent_id' => 'required|integer|exists:support_agents,id', 'amount' => 'required|integer|gt:0']);

        try {
            $trade = app(CoinMarketplace::class)->initiateBuyFromAgent(auth()->user(), SupportAgent::findOrFail($data['support_agent_id']), (string) $data['amount']);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return to_route('user.coin.trade', $trade->id)->withNotify([['success', 'Trade started. The agent will share payment details in chat.']]);
    }

    public function show(int $tradeId)
    {
        $trade = CoinTrade::findOrFail($tradeId);
        abort_unless($trade->isParticipant('user', auth()->id()), 403);
        $pageTitle = 'Trade #' . $trade->id;
        $myVendor = \Illuminate\Support\Facades\Schema::hasTable('coin_vendors')
            ? CoinVendor::where('user_id', auth()->id())->where('status', 'active')->first()
            : null;
        $vendorPaymentMethods = $myVendor?->encrypted_payment_methods ?: [];
        $counterparty = $this->counterpartyContactCard($trade, auth()->id());

        return view('Template::user.coin.trade', compact('pageTitle', 'trade', 'vendorPaymentMethods', 'counterparty'));
    }

    /**
     * The trade chat is always available, but a vendor/agent who also set up direct contact
     * channels (WhatsApp/Telegram/phone/email) should be reachable that way too - this resolves
     * the OTHER party in the trade (both sides are always type 'user'; whether that user also
     * happens to be a CoinVendor or a SupportAgent decides whether there's a contact card to
     * show at all) into the same display shape manual.blade.php already uses for a payment
     * agent, so the same widget concept now also covers coin marketplace trades.
     */
    private function counterpartyContactCard(CoinTrade $trade, int $viewerId): ?array
    {
        $counterpartyId = (int) ($trade->coin_seller_id == $viewerId ? $trade->coin_buyer_id : $trade->coin_seller_id);
        if (!$counterpartyId) return null;

        if (\Illuminate\Support\Facades\Schema::hasTable('coin_vendors')) {
            $vendor = CoinVendor::where('user_id', $counterpartyId)->first();
            if ($vendor && $this->hasAnyContactChannel($vendor->contact_methods)) {
                return [
                    'name' => $vendor->user?->username ?? 'Vendor',
                    'role' => 'Coin Vendor',
                    'avatar' => $vendor->user?->avatarUrl(),
                    'verified' => true, // every active CoinVendor is considered verified - see WinxPayDirectory::forBuyer()
                    'contact_methods' => $vendor->contact_methods,
                ];
            }
        }

        $agent = SupportAgent::where('user_id', $counterpartyId)->where('is_active', true)->first();
        if ($agent && $this->hasAnyContactChannel($agent->contact_methods)) {
            return [
                'name' => $agent->name,
                'role' => 'Support Agent',
                'avatar' => $agent->photo ? asset('assets/images/support-agents/' . basename($agent->photo)) : null,
                'verified' => (bool) $agent->is_verified,
                'contact_methods' => $agent->contact_methods,
            ];
        }

        return null;
    }

    private function hasAnyContactChannel($contactMethods): bool
    {
        if (!is_array($contactMethods)) return false;
        foreach ($contactMethods as $method) {
            if (!empty($method['enabled']) && !empty($method['value'])) return true;
        }
        return false;
    }

    public function claimPayment(Request $request, int $tradeId)
    {
        $request->validate(['proof' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf']);
        $trade = CoinTrade::findOrFail($tradeId);

        $proofPath = null;
        if ($file = $request->file('proof')) {
            $proofPath = $file->storeAs('coin-trade-proof/' . $tradeId, Str::uuid() . '.' . strtolower($file->extension() ?: 'bin'), 'local');
        }

        try {
            app(CoinMarketplace::class)->claimPayment($trade, 'user', auth()->id(), $proofPath);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Payment claim sent. Waiting for the other party to confirm receipt.']]);
    }

    public function confirmReceived(int $tradeId)
    {
        $trade = CoinTrade::findOrFail($tradeId);
        try {
            app(CoinMarketplace::class)->confirmReceived($trade, 'user', auth()->id());
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Trade completed - coins released.']]);
    }

    public function reportNotReceived(Request $request, int $tradeId)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $trade = CoinTrade::findOrFail($tradeId);
        try {
            app(CoinMarketplace::class)->reportNotReceived($trade, 'user', auth()->id(), $data['reason'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'This trade has been sent to a supervisor for review.']]);
    }

    public function reopenListing(int $tradeId)
    {
        $trade = CoinTrade::findOrFail($tradeId);
        try {
            app(CoinMarketplace::class)->reopenListing($trade, 'user', auth()->id());
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Put back on sale.']]);
    }

    private function assertCoinMode(): void
    {
        abort_unless((bool) gs('coin_mode'), 404);
    }
}
