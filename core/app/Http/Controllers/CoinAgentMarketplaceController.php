<?php

namespace App\Http\Controllers;

use App\Models\CoinListing;
use App\Models\CoinTrade;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CoinAgentMarketplaceController extends Controller
{
    public function index()
    {
        $pageTitle = 'Coin Marketplace';
        $agent = auth('support_agent')->user();
        $wallet = app(CoinLedger::class)->wallet('agent', $agent->id);
        $listings = CoinListing::where('status', 'on_sale')->latest()->paginate(getPaginate());
        $myTrades = CoinTrade::where(function ($query) use ($agent) {
            $query->where(['coin_seller_type' => 'agent', 'coin_seller_id' => $agent->id])
                ->orWhere(['coin_buyer_type' => 'agent', 'coin_buyer_id' => $agent->id]);
        })->latest()->paginate(getPaginate());

        return view('support-agent.coin.marketplace', compact('pageTitle', 'agent', 'wallet', 'listings', 'myTrades'));
    }

    public function toggleSelling()
    {
        $agent = auth('support_agent')->user();
        if (!$agent->is_payment_agent) {
            return back()->withNotify([['error', 'Only payment agents can sell coins.']]);
        }
        $agent->is_selling_coins = !$agent->is_selling_coins;
        $agent->save();

        return back()->withNotify([['success', $agent->is_selling_coins ? 'You are now selling coins - users can buy directly from you.' : 'You are no longer selling coins.']]);
    }

    public function claimListing(int $listingId)
    {
        $agent = auth('support_agent')->user();
        if (!$agent->is_payment_agent || !$agent->is_active) {
            return back()->withNotify([['error', 'Only active payment agents can buy coins from the marketplace.']]);
        }

        try {
            $trade = app(CoinMarketplace::class)->claimListing(CoinListing::findOrFail($listingId), $agent);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return to_route('support-agent.coin.trade', $trade->id)->withNotify([['success', 'Listing claimed. Share your payment details with the seller in chat.']]);
    }

    public function show(int $tradeId)
    {
        $agent = auth('support_agent')->user();
        $trade = CoinTrade::findOrFail($tradeId);
        abort_unless($trade->isParticipant('agent', $agent->id) || $agent->is_marketplace_supervisor, 403);
        $pageTitle = 'Trade #' . $trade->id;

        return view('support-agent.coin.trade', compact('pageTitle', 'trade'));
    }

    public function claimPayment(Request $request, int $tradeId)
    {
        $request->validate(['proof' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf']);
        $agent = auth('support_agent')->user();
        $trade = CoinTrade::findOrFail($tradeId);

        $proofPath = null;
        if ($file = $request->file('proof')) {
            $proofPath = $file->storeAs('coin-trade-proof/' . $tradeId, Str::uuid() . '.' . strtolower($file->extension() ?: 'bin'), 'local');
        }

        try {
            app(CoinMarketplace::class)->claimPayment($trade, 'agent', $agent->id, $proofPath);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Payment claim sent. Waiting for the seller to confirm receipt.']]);
    }

    public function confirmReceived(int $tradeId)
    {
        $agent = auth('support_agent')->user();
        $trade = CoinTrade::findOrFail($tradeId);
        try {
            app(CoinMarketplace::class)->confirmReceived($trade, 'agent', $agent->id);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Trade completed - coins released.']]);
    }

    public function reportNotReceived(Request $request, int $tradeId)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $agent = auth('support_agent')->user();
        $trade = CoinTrade::findOrFail($tradeId);
        try {
            app(CoinMarketplace::class)->reportNotReceived($trade, 'agent', $agent->id, $data['reason'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'This trade has been sent to a supervisor for review.']]);
    }
}
