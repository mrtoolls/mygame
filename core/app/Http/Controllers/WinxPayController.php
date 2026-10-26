<?php

namespace App\Http\Controllers;

use App\Models\SupportAgent;
use App\Support\AgentSpeed;

class WinxPayController extends Controller
{
    public function index()
    {
        $pageTitle = 'WinxPay Agents';
        $agents = SupportAgent::paymentAgents()->orderByDesc('is_verified')->orderBy('name')->get();
        $speedScores = app(AgentSpeed::class)->scoresFor($agents);
        $directoryItems = array_merge(
            app(\App\Support\WinxPayDirectory::class)->agentsForBuyer(auth()->user()),
            app(\App\Support\WinxPayDirectory::class)->forBuyer(auth()->user())
        );
        usort($directoryItems, fn ($a, $b) => $a['rate_per_coin'] <=> $b['rate_per_coin']);
        $coinMode = (bool) gs('coin_mode');

        return view('Template::winxpay.index', compact('pageTitle', 'agents', 'speedScores', 'directoryItems', 'coinMode'));
    }
}
