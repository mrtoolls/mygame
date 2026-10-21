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

        return view('Template::winxpay.index', compact('pageTitle', 'agents', 'speedScores'));
    }
}
