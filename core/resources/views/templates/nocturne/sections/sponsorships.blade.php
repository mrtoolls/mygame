@php
    $content = getContent('sponsorships.content', true);
    $sponsors = getContent('sponsorships.element', orderById: true);
@endphp
<section class="nocturne-sponsor-hero">
    <span class="nocturne-sponsor-hero__glow"></span>
    <div class="nocturne-sponsor-hero__copy">
        <span class="nocturne-sponsor-hero__kicker"><i class="ph-bold ph-handshake"></i>@lang('PARTNER WITH WINXPOT')</span>
        <h1>{{ __(@$content->data_values->heading ?: 'Sponsorships') }}</h1>
        <p>{{ __(@$content->data_values->subheading ?: 'Put your brand in front of an active, engaged gaming community.') }}</p>
        <a href="{{ auth()->check() ? route('user.sponsorships.index') : route('user.login') }}" class="nocturne-sponsor-cta"><i class="ph-bold ph-handshake"></i>@lang('Sponsor Winxpot Events')</a>
    </div>
    <span class="nocturne-sponsor-hero__art"><i class="ph-fill ph-handshake"></i></span>
</section>

<section class="nocturne-section nocturne-sponsor-benefits">
    <article><span><i class="ph-fill ph-users-three"></i></span><h3>@lang('Reach an Active Community')</h3><p>@lang('Get in front of thousands of engaged players across our live tables and social feed.')</p></article>
    <article><span><i class="ph-fill ph-megaphone-simple"></i></span><h3>@lang('Real Brand Visibility')</h3><p>@lang('Your logo featured on this page and called out across the events you support.')</p></article>
    <article><span><i class="ph-fill ph-chats-circle"></i></span><h3>@lang('A Direct Partnership')</h3><p>@lang('Work directly with our team to shape a sponsorship that fits your brand and budget.')</p></article>
</section>

<section class="nocturne-section">
    @if ($sponsors->count())
        <div class="nocturne-sponsor-grid">
            @foreach ($sponsors as $sponsor)
                <article class="nocturne-sponsor-card">
                    <span class="nocturne-sponsor-card__glow"></span>
                    <div class="nocturne-sponsor-card__logo"><img src="{{ getImage('assets/images/frontend/sponsorships/' . @$sponsor->data_values->image, '160x160') }}" alt="{{ __(@$sponsor->data_values->title) }}" loading="lazy"></div>
                    <h3>{{ __(@$sponsor->data_values->title) }}</h3>
                    <p>{{ __(@$sponsor->data_values->description) }}</p>
                    @if(!empty($sponsor->data_values->website_url))
                        <a href="{{ $sponsor->data_values->website_url }}" target="_blank" rel="noopener" class="nocturne-sponsor-card__link">@lang('Visit website') <i class="ph-bold ph-arrow-up-right"></i></a>
                    @endif
                </article>
            @endforeach
        </div>
    @else
        <div class="nocturne-sponsor-empty">
            <span class="nocturne-sponsor-empty__icon"><i class="ph-fill ph-trophy"></i></span>
            <h3>@lang('Be our first featured sponsor')</h3>
            <p>@lang('Our sponsor lineup is just getting started - brands supporting Winxpot events will be showcased right here.')</p>
            <a href="{{ auth()->check() ? route('user.sponsorships.index') : route('user.login') }}" class="nocturne-sponsor-cta nocturne-sponsor-cta--ghost"><i class="ph-bold ph-handshake"></i>@lang('Get Featured')</a>
        </div>
    @endif
</section>

<section class="nocturne-sponsor-footer-cta">
    <span class="nocturne-sponsor-footer-cta__glow"></span>
    <div><h3>@lang('Want to support a Winxpot event?')</h3><p>@lang('Tell us how you would like to sponsor us and our team will reach out.')</p></div>
    <a href="{{ auth()->check() ? route('user.sponsorships.index') : route('user.login') }}" class="nocturne-sponsor-cta"><i class="ph-bold ph-handshake"></i>@lang('Sponsor Winxpot Events')</a>
</section>
