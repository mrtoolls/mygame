@php
    $content = getContent('vip_club.content', true);
    $tiers = \App\Models\VipTier::active()->orderBy('min_wagered')->get();
    $authUser = auth()->user();
@endphp
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="ph-bold ph-crown"></i> {{ __(@$content->data_values->heading ?: 'VIP Club') }}</span>
        <p>{{ __(@$content->data_values->subheading) }}</p>
        @if ($authUser)
            <a class="nocturne-outline-btn" href="{{ route('user.vip.index') }}"><i class="ph-bold ph-chart-line-up"></i> @lang('View your progress')</a>
        @endif
    </div>
</section>

@if ($tiers->count())
    <section class="nocturne-section">
        <div class="nocturne-tier-grid">
            @foreach ($tiers as $tier)
                <div class="nocturne-tier-card">
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
    </section>
@else
    <section class="nocturne-section">
        <div class="card custom--card">
            <div class="card-body text-center">
                <h5 class="mb-0">@lang('VIP tiers have not been configured yet.')</h5>
            </div>
        </div>
    </section>
@endif
