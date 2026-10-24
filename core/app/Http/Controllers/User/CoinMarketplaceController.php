<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CoinListing;
use App\Models\CoinTrade;
use App\Models\SupportAgent;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
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
        $agents = SupportAgent::where('is_selling_coins', true)->where('is_active', true)->orderBy('name')->get();
        $myListings = CoinListing::where('seller_user_id', $user->id)->whereIn('status', ['on_sale', 'pending'])->latest()->get();
        $myTrades = CoinTrade::where(function ($query) use ($user) {
            $query->where(['coin_seller_type' => 'user', 'coin_seller_id' => $user->id])
                ->orWhere(['coin_buyer_type' => 'user', 'coin_buyer_id' => $user->id]);
        })->latest()->paginate(getPaginate());

        return view('Template::user.coin.marketplace', compact('pageTitle', 'wallet', 'agents', 'myListings', 'myTrades'));
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

        return view('Template::user.coin.trade', compact('pageTitle', 'trade'));
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
