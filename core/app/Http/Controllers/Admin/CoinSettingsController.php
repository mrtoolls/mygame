<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CoinRate;
use App\Services\CoinLedger;
use App\Services\CoinPricing;
use App\Services\CurrencyRateRefresher;
use Illuminate\Http\Request;

class CoinSettingsController extends Controller
{
    public function index()
    {
        $pageTitle = 'Coin Economy Settings';
        $general = gs();
        $rates = CoinRate::with('currency')->get()->keyBy('currency_id');
        $currencies = Currency::where('enabled', true)->orderBy('code')->get();
        $reconciliation = app(CoinLedger::class)->reconcile();

        return view('admin.coin.settings', compact('pageTitle', 'general', 'rates', 'currencies', 'reconciliation'));
    }

    public function pricing()
    {
        $pageTitle = 'Coin Pricing';
        $general = gs();
        $currencies = Currency::where('enabled', true)->orderBy('code')->get();
        $rates = CoinRate::all()->keyBy('currency_id');
        return view('admin.coin.pricing', compact('pageTitle', 'general', 'currencies', 'rates'));
    }

    public function saveGlobalMargins(Request $request)
    {
        $data = $request->validate(['buy_margin_percent' => 'required|numeric|min:0|max:100', 'sell_margin_percent' => 'required|numeric|min:0|max:100']);
        $general = gs();
        $general->coin_default_buy_margin_percent = $data['buy_margin_percent'];
        $general->coin_default_sell_margin_percent = $data['sell_margin_percent'];
        $general->save();
        $results = app(CoinPricing::class)->recomputeAll(auth('admin')->id());
        return redirect()->route('admin.coin.pricing')->with('coin_pricing_results', $results);
    }

    public function refreshPricing(CurrencyRateRefresher $refresher)
    {
        try {
            $refresh = $refresher->refresh(auth('admin')->id());
            return redirect()->route('admin.coin.pricing')->with('coin_refresh_result', $refresh);
        } catch (\Throwable $exception) {
            return redirect()->route('admin.coin.pricing')->with('coin_refresh_error', $exception->getMessage());
        }
    }

    public function saveCurrencyMargins(Request $request, int $currencyId)
    {
        $data = $request->validate(['buy_margin_override' => 'nullable|numeric|min:0|max:100', 'sell_margin_override' => 'nullable|numeric|min:0|max:100']);
        $currency = Currency::findOrFail($currencyId);
        $rate = CoinRate::firstOrCreate(['currency_id' => $currency->id]);
        $rate->buy_margin_override = $data['buy_margin_override'] ?? null;
        $rate->sell_margin_override = $data['sell_margin_override'] ?? null;
        $rate->save();
        try { app(CoinPricing::class)->recompute($currency, auth('admin')->id()); }
        catch (\InvalidArgumentException $exception) { $rate->status = 'paused'; $rate->pause_reason = $exception->getMessage(); $rate->save(); }
        return redirect()->route('admin.coin.pricing')->with('coin_pricing_results', app(CoinPricing::class)->recomputeAll(auth('admin')->id()));
    }

    /** Turning coin_mode ON requires an explicit confirmation checkbox and a live, passing reconciliation check - this is the point of no casual return once real trading starts. */
    public function toggleCoinMode(Request $request)
    {
        $request->validate(['enable' => 'required|boolean']);
        $general = gs();

        if (!$request->boolean('enable')) {
            $general->coin_mode = false;
            $general->save();
            return back()->withNotify([['success', 'Coin mode turned off. The platform is back on real-currency wallets.']]);
        }

        if (!$request->boolean('confirm')) {
            return back()->withNotify([['error', 'Check the confirmation box to turn coin mode on.']]);
        }

        $reconciliation = app(CoinLedger::class)->reconcile();
        if (!$reconciliation['ok']) {
            return back()->withNotify([['error', "Reconciliation does not balance (difference: {$reconciliation['difference']}) - coin mode cannot be turned on until this is resolved."]]);
        }

        $general->coin_mode = true;
        $general->coin_mode_confirmed_at = now();
        $general->save();

        // Make sure every enabled currency has a price (or is clearly marked paused with why)
        // the moment coin mode goes live, instead of players hitting a cold, unpriced currency.
        $results = app(CoinPricing::class)->recomputeAll();
        $paused = array_filter($results, fn ($r) => !$r['ok']);
        $notify = [['success', $paused ? 'Coin mode is now on. Review Coin Pricing for currencies that need attention.' : 'Coin mode is now on.']];

        return back()->withNotify($notify);
    }

    public function toggleTestKit(Request $request)
    {
        $request->validate(['enable' => 'required|boolean']);
        $general = gs();
        $general->coin_marketplace_test_kit_enabled = $request->boolean('enable');
        $general->save();

        return back()->withNotify([['success', $request->boolean('enable') ? 'Marketplace test kit enabled.' : 'Marketplace test kit disabled.']]);
    }

    public function toggleSingleApprover(Request $request)
    {
        $request->validate(['enable' => 'required|boolean']);
        $general = gs();
        $general->allow_single_approver = $request->boolean('enable');
        $general->save();

        $notify = $request->boolean('enable')
            ? ['success', 'Single-approver mode is ON - float grants and large penalties no longer require a second admin. For testing only.']
            : ['success', 'Single-approver mode is OFF. A second admin is required again for float grants and large penalties.'];
        return back()->withNotify([$notify]);
    }

    public function saveMargins(Request $request, int $currencyId)
    {
        $data = $request->validate([
            'buy_margin_percent' => 'required|numeric|min:0|max:100',
            'sell_margin_percent' => 'required|numeric|min:0|max:100',
        ]);

        $currency = Currency::findOrFail($currencyId);
        $rate = CoinRate::firstOrCreate(['currency_id' => $currency->id]);
        $rate->buy_margin_override = $data['buy_margin_percent'];
        $rate->sell_margin_override = $data['sell_margin_percent'];
        $rate->save();

        try {
            app(CoinPricing::class)->recompute($currency, auth('admin')->id());
        } catch (\InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', "Coin margins updated and price recomputed for {$currency->code}."]]);
    }

    public function recomputeAll()
    {
        return redirect()->route('admin.coin.pricing')->with('coin_pricing_results', app(CoinPricing::class)->recomputeAll(auth('admin')->id()));
    }
}
