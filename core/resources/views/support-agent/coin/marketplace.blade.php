<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Coin Marketplace · Winxpot Care</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
<link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime('assets/global/css/support-agent.css') }}">
<link rel="stylesheet" href="{{ asset('assets/global/css/support-agent-coin.css') }}?v={{ @filemtime('assets/global/css/support-agent-coin.css') }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <div class="support-topbar__right">
            <div class="support-agent-profile">@if($agent->photo)<img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="">@else<span>{{ strtoupper(substr($agent->name,0,1)) }}</span>@endif<strong>{{ $agent->name }}<small>@lang('Payment agent')</small></strong></div>
        </div>
    </header>

    <main class="coin-agent-shell">
        <div class="coin-agent-topbar">
            <span class="support-agent-eyebrow">COIN MARKETPLACE</span>
            <nav class="coin-agent-nav">
                <a href="{{ route('support-agent.coin.marketplace') }}" class="is-active"><i class="ph-fill ph-storefront"></i> @lang('Marketplace')</a>
                <a href="{{ route('support-agent.coin.disputes.index') }}"><i class="ph-fill ph-gavel"></i> @lang('Disputes')</a>
            </nav>
        </div>

        <div class="coin-agent-page">
            <div class="coin-agent-hero">
                <div>
                    <div class="coin-agent-hero__amount">{{ number_format($wallet->available_balance, 0) }} <small>@lang('coins available')</small></div>
                    <div class="coin-agent-hero__sub">@lang('Held in open trades'): {{ number_format($wallet->held_balance, 0) }}</div>
                </div>
                <form method="post" action="{{ route('support-agent.coin.selling.toggle') }}">
                    @csrf
                    <div class="coin-agent-toggle">
                        <span class="coin-agent-toggle-label{{ $agent->is_selling_coins ? ' is-on' : '' }}"><span class="coin-agent-toggle-dot"></span> {{ $agent->is_selling_coins ? __('Selling coins') : __('Not selling') }}</span>
                        <button type="submit" class="coin-agent-btn coin-agent-btn--{{ $agent->is_selling_coins ? 'outline' : 'primary' }}">{{ $agent->is_selling_coins ? __('Stop selling') : __('Start selling') }}</button>
                    </div>
                </form>
            </div>

            <div class="coin-agent-card">
                <div class="coin-agent-card__head">
                    <div><h2>@lang('Open listings')</h2><p>@lang('Sellers waiting for an agent to buy their coins.')</p></div>
                </div>
                @if($listings->count())
                    <div class="coin-agent-list">
                        @foreach($listings as $listing)
                            <div class="coin-agent-row">
                                <span class="coin-agent-row__avatar"><i class="ph-fill ph-user"></i></span>
                                <div class="coin-agent-row__body">
                                    <span class="coin-agent-row__title">{{ $listing->seller->username ?? ('#' . $listing->seller_user_id) }}</span>
                                    <span class="coin-agent-row__subtitle">{{ number_format($listing->amount, 0) }} @lang('coins')</span>
                                </div>
                                <div class="coin-agent-row__trailing">
                                    <form method="post" action="{{ route('support-agent.coin.listings.claim', $listing->id) }}">
                                        @csrf
                                        <button type="submit" class="coin-agent-btn coin-agent-btn--primary"><i class="ph-bold ph-shopping-cart-simple"></i> @lang('Buy')</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div style="margin-top:14px">{{ $listings->links() }}</div>
                @else
                    <div class="coin-agent-empty"><i class="ph-fill ph-tray" style="font-size:26px;color:var(--agent-green);display:block;margin-bottom:8px"></i>@lang('No listings right now.')</div>
                @endif
            </div>

            <div class="coin-agent-card">
                <div class="coin-agent-card__head">
                    <div><h2>@lang('Your trades')</h2></div>
                </div>
                @if($myTrades->count())
                    <div class="coin-agent-list">
                        @foreach($myTrades as $trade)
                            <a href="{{ route('support-agent.coin.trade', $trade->id) }}" class="coin-agent-row">
                                <span class="coin-agent-row__avatar"><i class="ph-fill ph-arrows-left-right"></i></span>
                                <div class="coin-agent-row__body">
                                    <span class="coin-agent-row__title">@lang('Trade') #{{ $trade->id }}</span>
                                    <span class="coin-agent-row__subtitle">{{ number_format($trade->amount, 0) }} @lang('coins')</span>
                                </div>
                                <div class="coin-agent-row__trailing">
                                    <span class="coin-agent-badge coin-agent-badge--{{ $trade->status }}">{{ str_replace('_', ' ', $trade->status) }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <div style="margin-top:14px">{{ $myTrades->links() }}</div>
                @else
                    <div class="coin-agent-empty"><i class="ph-fill ph-arrows-left-right" style="font-size:26px;color:var(--agent-green);display:block;margin-bottom:8px"></i>@lang('No trades yet.')</div>
                @endif
            </div>
        </div>
    </main>
</body>
</html>
