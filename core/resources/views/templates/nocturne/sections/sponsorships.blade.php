@php
    $content = getContent('sponsorships.content', true);
    $sponsors = getContent('sponsorships.element', orderById: true);
@endphp
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="ph-bold ph-handshake"></i> {{ __(@$content->data_values->heading ?: 'Sponsorships') }}</span>
        <p>{{ __(@$content->data_values->subheading) }}</p>
    </div>
    <a href="{{ auth()->check() ? route('user.sponsorships.index') : route('user.login') }}" class="nocturne-sponsor-cta"><i class="ph-bold ph-handshake"></i>@lang('Sponsor Winxpot Events')</a>
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
        <div class="nocturne-sponsor-empty"><i class="ph-fill ph-handshake"></i><h3>@lang('Our sponsor lineup is coming together')</h3><p>@lang('Brands supporting Winxpot events will be featured here.')</p></div>
    @endif

    <div class="nocturne-sponsor-footer-cta">
        <div><h3>@lang('Want to support a Winxpot event?')</h3><p>@lang('Tell us how you would like to sponsor us and our team will reach out.')</p></div>
        <a href="{{ auth()->check() ? route('user.sponsorships.index') : route('user.login') }}" class="nocturne-sponsor-cta"><i class="ph-bold ph-handshake"></i>@lang('Sponsor Winxpot Events')</a>
    </div>
</section>
