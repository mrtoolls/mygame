@php
    $hero = getContent('vip_member_hero.content', true)?->data_values;
    $privilegeContent = getContent('vip_member_privileges.content', true)?->data_values;
    $privileges = getContent('vip_member_privileges.element', orderById: true);
    $faqContent = getContent('vip_member_faq.content', true)?->data_values;
    $faqs = getContent('vip_member_faq.element', orderById: true);
    $nextFloor = $currentTier?->min_wagered ?? 0;
    $nextSpan = $nextTier ? max((float) $nextTier->min_wagered - (float) $nextFloor, 0.00000001) : 1;
    $progressPercent = $nextTier ? min(100, max(0, (($wagered - $nextFloor) / $nextSpan) * 100)) : 100;
@endphp
@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account nocturne-vip-page">
    @include('Template::partials.account_nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('VIP Club')</span></div>

        <section class="nocturne-vip-hero">
            @if(!empty($hero->hero_image))
                <img class="nocturne-vip-hero__image" src="{{ frontendImage('vip_member_hero', $hero->hero_image, '1400x500') }}" alt="{{ __($hero->heading ?? 'VIP Club') }}" fetchpriority="high">
            @else
                <div class="nocturne-vip-hero__placeholder"><i class="ph-fill ph-image"></i><span>@lang('VIP hero image here')</span><small>@lang('Upload the VIP banner from Admin → Frontend → Manage Sections')</small></div>
            @endif
            <div class="nocturne-vip-hero__shade"></div>
            <div class="nocturne-vip-hero__copy">
                <span class="nocturne-vip-kicker"><i class="ph-fill ph-crown-simple"></i>@lang('THE WINXPOT VIP EXPERIENCE')</span>
                <h1>{{ __($hero->heading ?? 'Welcome to VIP Club') }}</h1>
                <h2>{{ __($hero->subheading ?? 'A little extra for every step of your journey.') }}</h2>
                <p>{{ __($hero->description ?? 'Progress through the tiers, explore the benefits available to you and see what is next.') }}</p>
                @if(!empty($hero->partner_label))<span class="nocturne-vip-partner-label">{{ __($hero->partner_label) }}</span>@endif
            </div>
        </section>

        <section class="nocturne-vip-progress-panel" aria-labelledby="vip-progress-title">
            <div class="nocturne-vip-progress-panel__head">
                <div><span class="nocturne-vip-kicker">@lang('YOUR JOURNEY')</span><h2 id="vip-progress-title">@lang('Your VIP Progress')</h2></div>
                <div class="nocturne-vip-wagered"><small>@lang('Lifetime wagered')</small><strong>{{ showAmount($wagered) }}</strong></div>
            </div>
            <div class="nocturne-vip-tier-bridge">
                <article class="nocturne-vip-tier-stop is-current">
                    <span class="nocturne-vip-tier-stop__icon"><i class="ph-fill ph-medal"></i></span>
                    <div><small>@lang('CURRENT TIER')</small><strong>{{ __($currentTier?->name ?? 'Unranked') }}</strong><span>@lang('Your current VIP status')</span></div>
                    <b>@lang('CURRENT')</b>
                </article>
                <div class="nocturne-vip-bridge" aria-label="{{ number_format($progressPercent, 0) }}% @lang('progress')"><span style="width: {{ $progressPercent }}%"></span><i class="ph-bold ph-caret-right"></i></div>
                <article class="nocturne-vip-tier-stop {{ $nextTier ? 'is-next' : 'is-next is-max' }}">
                    <span class="nocturne-vip-tier-stop__icon"><i class="ph-fill {{ $nextTier ? 'ph-lock-key' : 'ph-trophy' }}"></i></span>
                    <div><small>{{ $nextTier ? __('NEXT MILESTONE') : __('TOP TIER REACHED') }}</small><strong>{{ __($nextTier?->name ?? $currentTier?->name ?? 'VIP') }}</strong><span>{{ $nextTier ? showAmount(max($nextTier->min_wagered - $wagered, 0)) . ' ' . __('to unlock') : __('You have reached the highest tier') }}</span></div>
                    @if($nextTier && $nextTier->bonus_amount > 0)<b>{{ showAmount($nextTier->bonus_amount) }} @lang('BONUS')</b>@endif
                </article>
            </div>
            <div class="nocturne-vip-progress-track"><span style="width: {{ $progressPercent }}%"></span></div>
            <div class="nocturne-vip-progress-panel__foot">
                <span>{{ number_format($progressPercent, 0) }}% @lang('to the next level')</span>
                @if($claimable > 0)
                    <form method="POST" action="{{ route('user.vip.claim.rakeback') }}">@csrf<button class="nocturne-vip-claim" type="submit"><i class="ph-bold ph-hand-coins"></i>@lang('Claim rakeback') <strong>{{ showAmount($claimable) }}</strong></button></form>
                @else
                    <a class="nocturne-vip-claim" href="{{ route('user.referrals') }}"><i class="ph-bold ph-gift"></i>@lang('Explore your rewards')</a>
                @endif
            </div>
        </section>

        <section class="nocturne-vip-tiers" aria-labelledby="vip-tiers-title">
            <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('LEVEL UP')</span><h2 id="vip-tiers-title">@lang('Tiers & Benefits')</h2><p>@lang('Every tier unlocks a new set of benefits. Your progress is always saved to your account.')</p></div><span class="nocturne-vip-swipe-note"><i class="ph-bold ph-arrows-left-right"></i>@lang('Swipe to explore')</span></div>
            @if($tiers->isEmpty())
                <div class="nocturne-vip-empty"><i class="ph-fill ph-crown-simple"></i><h3>@lang('VIP tiers are being prepared')</h3><p>@lang('Check back soon to explore your rewards.')</p></div>
            @else
                <div class="nocturne-vip-tier-rail" tabindex="0" aria-label="@lang('VIP tiers')">
                    @foreach($tiers as $index => $tier)
                        @php $isCurrent = $currentTier?->id === $tier->id; @endphp
                        <article class="nocturne-vip-level-card {{ $isCurrent ? 'is-current' : '' }} {{ $nextTier?->id === $tier->id ? 'is-next' : '' }}">
                            <div class="nocturne-vip-level-card__top"><span class="nocturne-vip-level-card__badge"><i class="ph-fill {{ $isCurrent ? 'ph-crown-simple' : 'ph-medal' }}"></i></span><span class="nocturne-vip-level-card__rank">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span></div>
                            <h3>{{ __($tier->name) }}</h3>
                            <span class="nocturne-vip-level-card__threshold">{{ showAmount($tier->min_wagered) }} <small>@lang('XP threshold')</small></span>
                            <ul>
                                @if($tier->bonus_amount > 0)<li><i class="ph-fill ph-check-circle"></i><span>{{ showAmount($tier->bonus_amount) }} @lang('tier bonus')</span></li>@endif
                                <li><i class="ph-fill ph-check-circle"></i><span>{{ showAmount($tier->rakeback_percent, currencyFormat: false) }}% @lang('daily rakeback')</span></li>
                                @if($tier->max_bet > 0)<li><i class="ph-fill ph-check-circle"></i><span>@lang('Max bet') {{ showAmount($tier->max_bet) }}</span></li>@endif
                                <li><i class="ph-fill ph-check-circle"></i><span>{{ $index === count($tiers) - 1 ? __('Exclusive VIP benefits') : __('Member promotions') }}</span></li>
                            </ul>
                            @if($isCurrent)<span class="nocturne-vip-level-card__state"><i class="ph-bold ph-check"></i>@lang('Your current tier')</span>@elseif($nextTier?->id === $tier->id)<span class="nocturne-vip-level-card__state">{{ showAmount(max($tier->min_wagered - $wagered, 0)) }} @lang('to unlock')</span>@endif
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        @if($privileges->isNotEmpty())
            <section class="nocturne-vip-perks" aria-labelledby="vip-perks-title">
                <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('MADE FOR OUR MEMBERS')</span><h2 id="vip-perks-title">{{ __($privilegeContent->heading ?? 'VIP Club Privileges') }}</h2><p>@lang('A closer look at the advantages you can unlock as you climb.')</p></div></div>
                <div class="nocturne-vip-perk-grid">
                    @foreach($privileges as $perk)
                        <article class="nocturne-vip-perk-card">
                            <span class="nocturne-vip-perk-card__art">@if(!empty($perk->data_values->icon_image))<img src="{{ frontendImage('vip_member_privileges', $perk->data_values->icon_image, '180x180') }}" alt="">@else<i class="ph-fill ph-image"></i><small>@lang('Benefit image here')</small>@endif</span>
                            <div><h3>{{ __($perk->data_values->title) }}</h3><p>{{ __($perk->data_values->description) }}</p></div>
                            <i class="ph-bold ph-arrow-up-right nocturne-vip-perk-card__arrow"></i>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if($faqs->isNotEmpty())
            <section class="nocturne-vip-faq" aria-labelledby="vip-faq-title">
                <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('GOOD TO KNOW')</span><h2 id="vip-faq-title">{{ __($faqContent->heading ?? 'VIP Club FAQ') }}</h2></div></div>
                <div class="nocturne-vip-faq-list">
                    @foreach($faqs as $faq)
                        <details class="nocturne-vip-faq-item"><summary>{{ __($faq->data_values->question) }}<i class="ph-bold ph-plus"></i></summary><div>{{ __($faq->data_values->answer) }}</div></details>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
