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
<div class="nocturne-account nocturne-vip-page nocturne-vip-page--full">
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
                <div class="nocturne-vip-wagered"><small>@lang('Lifetime wagered')</small><strong>{{ formatVipThreshold($wagered) }}</strong></div>
            </div>
            @if($tiers->isEmpty())
                <div class="nocturne-vip-empty"><i class="ph-fill ph-crown-simple"></i><h3>@lang('VIP tiers are being prepared')</h3><p>@lang('Check back soon to start earning rewards.')</p></div>
            @else
                <div class="nocturne-vip-tier-bridge">
                    <article class="nocturne-vip-tier-stop is-current">
                        <span class="nocturne-vip-tier-stop__icon">@if($currentTier?->badgeImageUrl())<img src="{{ $currentTier->badgeImageUrl() }}" alt="">@else<i class="ph-fill ph-medal"></i>@endif</span>
                        <div><small>@lang('CURRENT TIER')</small><strong>{{ __($currentTier?->name ?? 'Unranked') }}</strong><span>@lang('Your current VIP status')</span></div>
                        <b>@lang('CURRENT')</b>
                    </article>
                    <div class="nocturne-vip-bridge" aria-label="{{ number_format($progressPercent, 0) }}% @lang('progress')"><span style="width: {{ $progressPercent }}%"></span><i class="ph-bold ph-caret-right"></i></div>
                    <article class="nocturne-vip-tier-stop {{ $nextTier ? 'is-next' : 'is-next is-max' }}">
                        <span class="nocturne-vip-tier-stop__icon">@if($nextTier?->badgeImageUrl())<img src="{{ $nextTier->badgeImageUrl() }}" alt="">@else<i class="ph-fill {{ $nextTier ? 'ph-lock-key' : 'ph-trophy' }}"></i>@endif</span>
                        <div><small>{{ $nextTier ? __('NEXT MILESTONE') : __('TOP TIER REACHED') }}</small><strong>{{ __($nextTier?->name ?? $currentTier?->name ?? 'VIP') }}</strong><span>{{ $nextTier ? formatVipThreshold(max($nextTier->min_wagered - $wagered, 0)) . ' ' . __('to unlock') : __('You have reached the highest tier') }}</span></div>
                        @if($nextTier && $nextTier->bonus_amount > 0)<b>{{ formatCoinOrUsd($nextTier->bonus_amount) }} @lang('BONUS')</b>@endif
                    </article>
                </div>
                <div class="nocturne-vip-progress-track"><span style="width: {{ $progressPercent }}%"></span></div>
            @endif
            <div class="nocturne-vip-progress-panel__foot">
                @if($tiers->isNotEmpty())<span>{{ number_format($progressPercent, 0) }}% @lang('to the next level')</span>@endif
                @if($claimable > 0)
                    <form method="POST" action="{{ route('user.vip.claim.rakeback') }}">@csrf<button class="nocturne-vip-claim" type="submit"><i class="ph-bold ph-hand-coins"></i>@lang('Claim rakeback') <strong>{{ gs('coin_mode') ? coinAmountText($claimable) : showAmount($claimable) }}</strong></button></form>
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
                        <article class="nocturne-vip-slide {{ $isCurrent ? 'is-current' : '' }} {{ $nextTier?->id === $tier->id ? 'is-next' : '' }}">
                            <div class="nocturne-vip-slide__badgewrap">
                                <span class="nocturne-vip-slide__badge">@if($tier->badgeImageUrl())<img src="{{ $tier->badgeImageUrl() }}" alt="">@else<i class="ph-fill {{ $isCurrent ? 'ph-crown-simple' : 'ph-medal' }}"></i>@endif</span>
                                <h3 class="nocturne-vip-slide__name">{{ __($tier->name) }}</h3>
                            </div>
                            <div class="nocturne-vip-level-card">
                                <span class="nocturne-vip-level-card__threshold">{{ formatVipThreshold($tier->min_wagered) }} <small>@lang('XP threshold')</small></span>
                                <ul>
                                    @if($tier->bonus_amount > 0)<li><i class="ph-fill ph-check-circle"></i><span>{{ formatCoinOrUsd($tier->bonus_amount) }} @lang('level-up bonus')</span></li>@endif
                                    <li><i class="ph-fill ph-check-circle"></i><span>{{ showAmount($tier->rakeback_percent, currencyFormat: false) }}% @lang('rakeback (claim anytime)')</span></li>
                                    @if($tier->daily_reward_amount > 0)<li><i class="ph-fill ph-check-circle"></i><span>{{ formatCoinOrUsd($tier->daily_reward_amount) }} @lang('daily free reward')</span></li>@endif
                                    @if($tier->birthday_bonus_amount > 0)<li><i class="ph-fill ph-check-circle"></i><span>{{ formatCoinOrUsd($tier->birthday_bonus_amount) }} @lang('birthday bonus')</span></li>@endif
                                    @if($tier->withdrawal_processing_hours > 0 && !gs('coin_mode'))<li><i class="ph-fill ph-check-circle"></i><span>@lang('Withdrawals up to') {{ $tier->withdrawal_processing_hours }}h</span></li>@endif
                                    @if($tier->monthly_withdrawal_limit > 0 && !gs('coin_mode'))<li><i class="ph-fill ph-check-circle"></i><span>{{ formatUsdInWalletCurrency($tier->monthly_withdrawal_limit) }} @lang('monthly withdrawal limit')</span></li>@endif
                                    @if($tier->max_bet > 0)<li><i class="ph-fill ph-check-circle"></i><span>@lang('Max bet') {{ formatCoinOrUsd($tier->max_bet) }}</span></li>@endif
                                    @if($tier->referral_bonus_boost_percent > 0)<li><i class="ph-fill ph-check-circle"></i><span>+{{ showAmount($tier->referral_bonus_boost_percent, currencyFormat: false) }}% @lang('referral bonus boost')</span></li>@endif
                                    @if($tier->support_level)<li><i class="ph-fill ph-check-circle"></i><span>{{ __($tier->support_level) }} @lang('support')</span></li>@endif
                                    <li><i class="ph-fill ph-check-circle"></i><span>{{ $tier->exclusive_promotions ? __($tier->exclusive_promotions) . ' ' . __('exclusive promotions') : ($index === count($tiers) - 1 ? __('Exclusive VIP benefits') : __('Member promotions')) }}</span></li>
                                </ul>
                                @if($isCurrent)<span class="nocturne-vip-level-card__state"><i class="ph-bold ph-check"></i>@lang('Your current tier')</span>@elseif($nextTier?->id === $tier->id)<span class="nocturne-vip-level-card__state">{{ formatVipThreshold(max($tier->min_wagered - $wagered, 0)) }} @lang('to unlock')</span>@endif
                            </div>
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

@push('style')
<style>
/* VIP tier rail: badge+name float above a connecting line, card panel below.
   True N-per-view slider sizing, with the next card peeking at the edge so
   it's visually obvious there's more to swipe. No visible scrollbar - the
   peek + "Swipe to explore" label are the only affordance. */
.nocturne-vip-tier-rail { grid-auto-columns: calc((100% - 26px) / 3.3) !important; align-items: stretch; scrollbar-width: none; }
.nocturne-vip-tier-rail::-webkit-scrollbar { display: none !important; height: 0 !important; }
.nocturne-vip-slide { display: flex; flex-direction: column; align-items: center; min-width: 0; scroll-snap-align: start; }
.nocturne-vip-slide__badgewrap { position: relative; display: flex; flex-direction: column; align-items: center; gap: 8px; width: 100%; padding: 6px 0 18px; flex: 0 0 auto; }
.nocturne-vip-slide__badgewrap::before { content: ""; position: absolute; top: 23px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, var(--nocturne-line) 15%, var(--nocturne-line) 85%, transparent); z-index: 0; }
.nocturne-vip-slide__badge { position: relative; z-index: 1; display: grid; place-items: center; width: 46px; height: 46px; border-radius: 50%; background: radial-gradient(circle at 30% 25%, rgba(0,239,135,.35), var(--nocturne-raised) 70%); border: 2px solid rgba(0,239,135,.5); color: var(--nocturne-green); font-size: 21px; box-shadow: 0 8px 18px rgba(0,0,0,.35); overflow: hidden; }
.nocturne-vip-slide__badge img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.nocturne-vip-slide.is-current .nocturne-vip-slide__badge { border-color: var(--nocturne-green); box-shadow: 0 0 0 1px rgba(0,239,135,.4), 0 8px 18px rgba(0,239,135,.25); }
.nocturne-vip-slide__name { position: relative; z-index: 1; margin: 0; color: var(--nocturne-text); font-size: 15px; font-weight: 900; background: var(--nocturne-bg); padding: 0 8px; white-space: nowrap; }
.nocturne-vip-slide .nocturne-vip-level-card { width: 100%; flex: 1; display: flex; flex-direction: column; }
.nocturne-vip-slide .nocturne-vip-level-card__state { margin-top: auto; padding-top: 12px; }

@media(max-width: 767px) {
    /* Each tier card lists up to 9 perk lines - 2+ per view left them too
       narrow to read (wrapping on nearly every word). One full card plus a
       peek of the next reads far better on a phone. */
    .nocturne-vip-tier-rail { grid-auto-columns: calc(100% / 1.15) !important; }
    .nocturne-vip-slide__badge { width: 38px; height: 38px; font-size: 18px; }
    .nocturne-vip-slide__name { font-size: 12.5px; }

    /* The desktop "bridge" connector between the current/next tier stops is
       a wide horizontal bar rotated 90deg to read as vertical on mobile -
       but its length is still based on its (now irrelevant) width, so it
       way overshoots the small gap between the stacked cards and cuts a
       stray line straight through them. It's purely decorative (the actual
       progress is already shown by the "% to the next level" text and the
       full-width track bar below), so just drop it on mobile instead of
       trying to re-orient a horizontal fill into a vertical one. */
    .nocturne-vip-bridge { display: none !important; }
}
</style>
@endpush
