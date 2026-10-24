<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Coin Trade Disputes · Winxpot Care</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
<link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime('assets/global/css/support-agent.css') }}">
<link rel="stylesheet" href="{{ asset('assets/global/css/support-agent-coin.css') }}?v={{ @filemtime('assets/global/css/support-agent-coin.css') }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.coin.marketplace') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <span class="support-online-pill"><i></i> @lang('Supervisor')</span>
    </header>

    <main class="coin-agent-shell">
        <div class="coin-agent-topbar">
            <span class="support-agent-eyebrow">DISPUTE QUEUE</span>
            <nav class="coin-agent-nav"><a href="{{ route('support-agent.coin.marketplace') }}"><i class="ph-bold ph-arrow-left"></i> @lang('Marketplace')</a></nav>
        </div>

        <div class="coin-agent-page">
            @forelse($disputed as $trade)
                <div class="coin-dispute-card">
                    <div class="coin-dispute-card__head">
                        <div>
                            <strong style="font-size:15px">@lang('Trade') #{{ $trade->id }} &middot; {{ number_format($trade->amount, 0) }} @lang('coins')</strong>
                            <div class="coin-dispute-card__parties">
                                <span><i class="ph-fill ph-arrow-up-right" style="color:#ffbf5c"></i> {{ $trade->coin_seller_type }}#{{ $trade->coin_seller_id }} ({{ __('seller') }})</span>
                                <span><i class="ph-fill ph-arrow-down-left" style="color:var(--agent-green)"></i> {{ $trade->coin_buyer_type }}#{{ $trade->coin_buyer_id }} ({{ __('buyer') }})</span>
                            </div>
                        </div>
                        <span class="coin-agent-badge coin-agent-badge--disputed">@lang('Disputed')</span>
                    </div>
                    <div style="color:var(--agent-muted);font-size:11px">@lang('Opened by') {{ $trade->dispute_opened_by_type }}#{{ $trade->dispute_opened_by_id }} @lang('at') {{ $trade->dispute_opened_at }}</div>

                    <div class="coin-dispute-divider"></div>

                    <form method="post" action="{{ route('support-agent.coin.disputes.resolve', $trade->id) }}" class="coin-agent-form-row">
                        @csrf
                        <div class="coin-agent-field" style="flex:1.4 1 220px">
                            <label>@lang('Decision')</label>
                            <select name="decision" required>
                                <option value="release_to_buyer">@lang('Release coins to buyer (payment was made)')</option>
                                <option value="return_to_seller">@lang('Return coins to seller (no payment made)')</option>
                            </select>
                        </div>
                        <div class="coin-agent-field" style="flex:2 1 260px">
                            <label>@lang('Reason')</label>
                            <input type="text" name="reason" required placeholder="@lang('Required')">
                        </div>
                        <button type="submit" class="coin-agent-btn coin-agent-btn--primary"><i class="ph-bold ph-gavel"></i> @lang('Resolve')</button>
                    </form>

                    <form method="post" action="{{ route('support-agent.coin.disputes.penalize', $trade->id) }}" class="coin-agent-form-row">
                        @csrf
                        <div class="coin-agent-field" style="flex:0 0 110px">
                            <label>@lang('Offender')</label>
                            <select name="offender_type" required><option value="user">@lang('User')</option><option value="agent">@lang('Agent')</option></select>
                        </div>
                        <div class="coin-agent-field" style="flex:0 0 110px">
                            <label>@lang('ID')</label>
                            <input type="number" name="offender_id" required>
                        </div>
                        <div class="coin-agent-field" style="flex:0 0 150px">
                            <label>@lang('Tier')</label>
                            <select name="tier" required><option value="1">@lang('Tier 1 — 10%')</option><option value="2">@lang('Tier 2 — 25%')</option><option value="3">@lang('Tier 3 — 50%')</option></select>
                        </div>
                        <div class="coin-agent-field" style="flex:2 1 220px">
                            <label>@lang('Reason')</label>
                            <input type="text" name="reason" required placeholder="@lang('Required')">
                        </div>
                        <button type="submit" class="coin-agent-btn coin-agent-btn--danger"><i class="ph-bold ph-warning"></i> @lang('Issue penalty')</button>
                    </form>
                </div>
            @empty
                <div class="coin-agent-card">
                    <div class="coin-agent-empty"><i class="ph-fill ph-confetti" style="font-size:28px;color:var(--agent-green);display:block;margin-bottom:8px"></i>@lang('No open disputes.')</div>
                </div>
            @endforelse
            <div>{{ $disputed->links() }}</div>
        </div>
    </main>
</body>
</html>
