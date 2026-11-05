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
        $myListings = CoinListing::where('seller_user_id', $user->id)->whereIn('status', ['on_sale', 'pending'])->latest()->get();
        $myTrades = CoinTrade::where(function ($query) use ($user) {
            $query->where(['coin_seller_type' => 'user', 'coin_seller_id' => $user->id])
                ->orWhere(['coin_buyer_type' => 'user', 'coin_buyer_id' => $user->id]);
        })->latest()->paginate(getPaginate());

        return view('Template::user.coin.marketplace', compact('pageTitle', 'wallet', 'agents', 'vendors', 'minimumTradeAmount', 'maximumTradeAmount', 'listings', 'listingVendorIds', 'listingRates', 'myListings', 'myTrades'));
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

        return view('Template::user.coin.trade', compact('pageTitle', 'trade', 'vendorPaymentMethods'));
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
