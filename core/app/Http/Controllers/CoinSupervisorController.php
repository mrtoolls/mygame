<?php

namespace App\Http\Controllers;

use App\Models\CoinTrade;
use App\Models\CoinVendor;
use App\Services\CoinMarketplace;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Agent-panel mirror of Admin\CoinDisputeController, for a SupportAgent with is_marketplace_supervisor. */
class CoinSupervisorController extends Controller
{
    public function index()
    {
        $agent = auth('support_agent')->user();
        abort_unless($agent->is_marketplace_supervisor, 403);

        $pageTitle = 'Coin Trade Disputes';
        $disputed = CoinTrade::where('status', 'disputed')->with(['events', 'messages', 'penalties'])->latest('dispute_opened_at')->paginate(getPaginate());
        $partyIds = $disputed->getCollection()->flatMap(fn ($trade) => [
            $trade->coin_seller_type === 'user' ? (int) $trade->coin_seller_id : null,
            $trade->coin_buyer_type === 'user' ? (int) $trade->coin_buyer_id : null,
        ])->filter()->unique();
        $vendorUserIds = \Illuminate\Support\Facades\Schema::hasTable('coin_vendors')
            ? CoinVendor::whereIn('user_id', $partyIds)->pluck('user_id')->map(fn ($id) => (int) $id)->all()
            : [];

        return view('support-agent.coin.disputes', compact('pageTitle', 'disputed', 'vendorUserIds'));
    }

    public function resolve(Request $request, int $tradeId)
    {
        $agent = auth('support_agent')->user();
        abort_unless($agent->is_marketplace_supervisor, 403);

        $data = $request->validate([
            'decision' => 'required|in:release_to_buyer,return_to_seller',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            app(CoinMarketplace::class)->resolveDispute(CoinTrade::findOrFail($tradeId), $data['decision'], 'agent', $agent->id, $data['reason']);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Dispute resolved.']]);
    }

    public function penalize(Request $request, int $tradeId)
    {
        $agent = auth('support_agent')->user();
        abort_unless($agent->is_marketplace_supervisor, 403);

        $data = $request->validate([
            'offender_type' => 'required|in:user,agent',
            'offender_id' => 'required|integer',
            'tier' => 'required|integer|between:1,3',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            app(CoinMarketplace::class)->issuePenalty(
                CoinTrade::findOrFail($tradeId),
                $data['offender_type'],
                $data['offender_id'],
                $data['tier'],
                $data['reason'],
                'agent',
                $agent->id
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Penalty issued.']]);
    }
}
