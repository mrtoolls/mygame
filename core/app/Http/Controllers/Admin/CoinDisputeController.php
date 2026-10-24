<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinTrade;
use App\Services\CoinMarketplace;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Admin side of dispute resolution and penalties. A SupportAgent with is_marketplace_supervisor
 * can do the same two actions from the agent panel (CoinSupervisorController) - both ultimately
 * call the same CoinMarketplace methods, which enforce the supervisor/admin check themselves.
 */
class CoinDisputeController extends Controller
{
    public function index()
    {
        $pageTitle = 'Coin Trade Disputes';
        $disputed = CoinTrade::where('status', 'disputed')->with(['events', 'messages', 'penalties'])->latest('dispute_opened_at')->paginate(getPaginate());

        return view('admin.coin.disputes', compact('pageTitle', 'disputed'));
    }

    public function resolve(Request $request, int $tradeId)
    {
        $data = $request->validate([
            'decision' => 'required|in:release_to_buyer,return_to_seller',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            app(CoinMarketplace::class)->resolveDispute(CoinTrade::findOrFail($tradeId), $data['decision'], 'admin', auth('admin')->id(), $data['reason']);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Dispute resolved.']]);
    }

    public function penalize(Request $request, int $tradeId)
    {
        $data = $request->validate([
            'offender_type' => 'required|in:user,agent',
            'offender_id' => 'required|integer',
            'tier' => 'required|integer|between:1,3',
            'reason' => 'required|string|max:1000',
            'second_approver_type' => 'nullable|in:admin,agent',
            'second_approver_id' => 'nullable|integer',
        ]);

        try {
            app(CoinMarketplace::class)->issuePenalty(
                CoinTrade::findOrFail($tradeId),
                $data['offender_type'],
                $data['offender_id'],
                $data['tier'],
                $data['reason'],
                'admin',
                auth('admin')->id(),
                $data['second_approver_type'] ?? null,
                $data['second_approver_id'] ?? null
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Penalty issued.']]);
    }
}
