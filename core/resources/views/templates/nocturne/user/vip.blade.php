@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('VIP Club')</h1>

    @if ($tiers->isEmpty())
        <div class="card custom--card">
            <div class="card-body text-center">
                <h5 class="mb-0">@lang('VIP tiers have not been configured yet.')</h5>
            </div>
        </div>
    @else
        <div class="nocturne-vip-progress">
            <div class="nocturne-vip-progress__head">
                <div>
                    <span>@lang('Your Tier')</span>
                    <strong>{{ $currentTier?->name ?? __('Unranked') }}</strong>
                </div>
                <div class="nocturne-vip-progress__wagered">
                    <span>@lang('Lifetime Wagered')</span>
                    <strong>{{ showAmount($wagered) }}</strong>
                </div>
            </div>
            @if ($nextTier)
                @php
                    $floor = $currentTier?->min_wagered ?? 0;
                    $span = max($nextTier->min_wagered - $floor, 0.00000001);
                    $percent = min(100, max(0, (($wagered - $floor) / $span) * 100));
                @endphp
                <div class="nocturne-vip-progress__bar"><span style="width: {{ $percent }}%"></span></div>
                <p class="nocturne-vip-progress__note">{{ showAmount($nextTier->min_wagered - $wagered) }} @lang('more wagered to reach') <strong>{{ $nextTier->name }}</strong></p>
            @else
                <div class="nocturne-vip-progress__bar"><span style="width: 100%"></span></div>
                <p class="nocturne-vip-progress__note">@lang("You've reached the highest VIP tier")</p>
            @endif
        </div>

        <div class="card custom--card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <p class="mb-0">@lang('Your rakeback accrues as you play and can be claimed from the Bonuses page.')</p>
                <a href="{{ route('user.referrals') }}" class="btn btn--dark btn--sm">@lang('Go to Bonuses')</a>
            </div>
        </div>

        <h4 class="mb-3">@lang('Tiers & Benefits')</h4>
        <div class="nocturne-tier-grid">
            @foreach ($tiers as $tier)
                <div class="nocturne-tier-card {{ $currentTier?->id === $tier->id ? 'is-current' : '' }}">
                    @if ($currentTier?->id === $tier->id)
                        <span class="nocturne-tier-card__current">@lang('Current')</span>
                    @endif
                    <i class="ph-bold ph-crown nocturne-tier-card__icon"></i>
                    <h5>{{ __($tier->name) }}</h5>
                    <ul>
                        <li>{{ showAmount($tier->min_wagered) }} @lang('wagered to unlock')</li>
                        @if ($tier->bonus_amount > 0)
                            <li>{{ showAmount($tier->bonus_amount) }} @lang('welcome bonus')</li>
                        @endif
                        <li>{{ showAmount($tier->rakeback_percent, currencyFormat: false) }}% @lang('daily rakeback')</li>
                        @if ($tier->max_bet)
                            <li>{{ showAmount($tier->max_bet) }} @lang('max bet limit')</li>
                        @endif
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
        </div>
    </div>
@endsection
