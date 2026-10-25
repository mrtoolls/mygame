@extends('admin.layouts.app')

@section('panel')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/coin-admin.css') }}?v={{ @filemtime('assets/admin/css/coin-admin.css') }}">

    <div class="row g-4">
        @if($general->allow_single_approver)
            <div class="col-12">
                <div class="alert alert--warning d-flex align-items-center gap-2 mb-0">
                    <i class="las la-exclamation-triangle fs-4"></i>
                    <div><strong>Single-approver mode is ON.</strong> Float grants and large penalties can be approved by the same admin who requested them. Testing only — turn it off before any real money is involved.</div>
                </div>
            </div>
        @endif

        @php
            $unpriced = $currencies->filter(function ($currency) use ($rates, $general) {
                $rate = $rates->get($currency->id);
                return !$rate || $rate->status === 'paused' || !$rate->buy_price || !$currency->last_updated_at || $currency->last_updated_at->lt(now()->subHours((int) ($general->coin_rate_stale_hours ?? 48)));
            });
        @endphp
        @if($unpriced->isNotEmpty())
            <div class="col-12"><div class="alert alert--warning mb-0"><strong>{{ $unpriced->count() }} currencies need pricing attention.</strong> <a href="{{ route('admin.coin.pricing') }}">Review Coin Pricing and refresh rates</a>.</div></div>
        @endif
        <div class="col-12">
            <div class="coin-admin-hero">
                <div>
                    <span class="coin-admin-hero__eyebrow"><i class="las la-coins"></i> Coin Economy</span>
                    <h1 class="coin-admin-hero__title">Command Center</h1>
                    <p class="coin-admin-hero__sub">Pricing, mode, and the live health of your coin ledger — all in one place.</p>
                </div>
                <div class="coin-admin-hero__status {{ $general->coin_mode ? 'is-on' : '' }}">
                    <span class="coin-admin-hero__status-dot"></span>
                    <div><strong>{{ $general->coin_mode ? 'LIVE' : 'OFFLINE' }}</strong><span>Coin mode is {{ $general->coin_mode ? 'on' : 'off' }}</span></div>
                </div>
            </div>
        </div>

        <div class="col-xxl-3 col-sm-6">
            <div class="coin-admin-stat">
                <span class="coin-admin-stat__icon coin-admin-stat__icon--{{ $general->coin_mode ? 'success' : 'primary' }}"><i class="las la-power-off"></i></span>
                <div class="coin-admin-stat__body"><span class="coin-admin-stat__value">{{ $general->coin_mode ? 'ON' : 'OFF' }}</span><span class="coin-admin-stat__label">Coin Mode</span></div>
            </div>
        </div>
        <div class="col-xxl-3 col-sm-6">
            <div class="coin-admin-stat">
                <span class="coin-admin-stat__icon coin-admin-stat__icon--{{ $reconciliation['ok'] ? 'success' : 'danger' }}"><i class="las la-scale-balanced"></i></span>
                <div class="coin-admin-stat__body"><span class="coin-admin-stat__value">{{ $reconciliation['ok'] ? 'OK' : 'MISMATCH' }}</span><span class="coin-admin-stat__label">Reconciliation</span></div>
            </div>
        </div>
        <div class="col-xxl-3 col-sm-6">
            <div class="coin-admin-stat">
                <span class="coin-admin-stat__icon coin-admin-stat__icon--primary"><i class="las la-chart-pie"></i></span>
                <div class="coin-admin-stat__body"><span class="coin-admin-stat__value">{{ number_format($reconciliation['circulating'], 0) }}</span><span class="coin-admin-stat__label">Circulating Supply</span></div>
            </div>
        </div>
        <div class="col-xxl-3 col-sm-6">
            <div class="coin-admin-stat">
                <span class="coin-admin-stat__icon coin-admin-stat__icon--warning"><i class="las la-vault"></i></span>
                <div class="coin-admin-stat__body"><span class="coin-admin-stat__value">{{ number_format($reconciliation['platform_balance'], 0) }}</span><span class="coin-admin-stat__label">Platform Ledger</span></div>
            </div>
        </div>

        <div class="col-12">
            <div class="coin-admin-panel">
                <div class="coin-admin-panel__head">
                    <h2><i class="las la-sliders-h"></i> Economy Controls</h2>
                </div>

                <div class="coin-admin-toggle-row">
                    <div class="coin-admin-toggle-row__copy">
                        <strong>Coin Mode</strong>
                        <span>Gateway purchases, games, bonuses and VIP all switch to coins. Requires a passing reconciliation check.</span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="coin-admin-status-pill coin-admin-status-pill--{{ $general->coin_mode ? 'on' : 'off' }}">{{ $general->coin_mode ? 'On' : 'Off' }}</span>
                        @if(!$general->coin_mode)
                            <form method="post" action="{{ route('admin.coin.mode.toggle') }}" class="d-flex align-items-center gap-2 flex-wrap mb-0">
                                @csrf
                                <input type="hidden" name="enable" value="1">
                                <label class="form-check-label small d-flex align-items-center gap-1 mb-0"><input type="checkbox" name="confirm" value="1" class="form-check-input"> I understand this turns on real coin trading</label>
                                <button type="submit" class="btn btn--primary btn-sm"><i class="las la-power-off"></i> Turn On</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.coin.mode.toggle') }}" class="confirmationBtn mb-0" data-question="Turn coin mode off? Everything goes back to real-currency wallets.">
                                @csrf
                                <input type="hidden" name="enable" value="0">
                                <button type="submit" class="btn btn-outline--danger btn-sm"><i class="las la-power-off"></i> Turn Off</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="coin-admin-toggle-row">
                    <div class="coin-admin-toggle-row__copy">
                        <strong>Single-Approver Mode</strong>
                        <span>Testing only — lets one admin approve their own float grants and large penalties.</span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="coin-admin-status-pill coin-admin-status-pill--{{ $general->allow_single_approver ? 'warn' : 'off' }}">{{ $general->allow_single_approver ? 'On' : 'Off' }}</span>
                        <form method="post" action="{{ route('admin.coin.single-approver.toggle') }}" class="mb-0">
                            @csrf
                            <input type="hidden" name="enable" value="{{ $general->allow_single_approver ? 0 : 1 }}">
                            <button type="submit" class="btn btn-outline--secondary btn-sm">{{ $general->allow_single_approver ? 'Disable' : 'Enable' }}</button>
                        </form>
                    </div>
                </div>

                <div class="coin-admin-toggle-row">
                    <div class="coin-admin-toggle-row__copy">
                        <strong>Marketplace Test Kit</strong>
                        <span>Spin up test agents, supervisors and sample trades to try every flow by hand.</span>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span class="coin-admin-status-pill coin-admin-status-pill--{{ $general->coin_marketplace_test_kit_enabled ? 'warn' : 'off' }}">{{ $general->coin_marketplace_test_kit_enabled ? 'On' : 'Off' }}</span>
                        <form method="post" action="{{ route('admin.coin.test-kit.toggle') }}" class="mb-0">
                            @csrf
                            <input type="hidden" name="enable" value="{{ $general->coin_marketplace_test_kit_enabled ? 0 : 1 }}">
                            <button type="submit" class="btn btn-outline--secondary btn-sm">{{ $general->coin_marketplace_test_kit_enabled ? 'Disable' : 'Enable' }}</button>
                        </form>
                    </div>
                </div>

                <div class="text-end mt-3">
                    <form method="post" action="{{ route('admin.coin.recompute') }}" class="d-inline mb-0">
                        @csrf
                        <a href="{{ route('admin.coin.pricing') }}" class="btn btn-outline--primary btn-sm"><i class="las la-coins"></i> Open Coin Pricing</a>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="coin-admin-panel">
                <div class="coin-admin-panel__head"><h2><i class="las la-compass"></i> Quick Links</h2></div>
                <div class="coin-admin-quicklinks">
                    <a href="{{ route('admin.coin.float.index') }}" class="coin-admin-quicklink"><i class="las la-hand-holding-usd"></i><span><strong>Float Grants</strong><span>Agent coin top-ups</span></span></a>
                    <a href="{{ route('admin.coin.disputes.index') }}" class="coin-admin-quicklink"><i class="las la-gavel"></i><span><strong>Trade Disputes</strong><span>Resolve &amp; penalize</span></span></a>
                    <a href="{{ route('admin.coin.cutover.index') }}" class="coin-admin-quicklink"><i class="las la-exchange-alt"></i><span><strong>Cutover Tool</strong><span>Convert real balances</span></span></a>
                    @if($general->coin_marketplace_test_kit_enabled)
                        <a href="{{ route('admin.coin.test-kit.index') }}" class="coin-admin-quicklink"><i class="las la-flask"></i><span><strong>Test Kit</strong><span>Sandbox accounts</span></span></a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12"><div class="coin-admin-panel d-flex flex-wrap align-items-center justify-content-between gap-3"><div><h2 class="mb-1"><i class="las la-money-bill-wave"></i> Coin Pricing</h2><p class="mb-0">Manage global margins, exchange rates, and per-currency prices in one place.</p></div><a class="btn btn--primary" href="{{ route('admin.coin.pricing') }}">Open Coin Pricing <i class="las la-arrow-right"></i></a></div></div>    </div>
@endsection
