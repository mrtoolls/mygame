@php
    $content = getContent('promotions.content', true)?->data_values;
    $promotions = getContent('promotions.element', orderById: true);
    $vipPromotions = $promotions->filter(fn ($promo) => strtolower(trim((string) ($promo->data_values->audience ?? ''))) === 'vip');
    $currentPromotions = $promotions->reject(fn ($promo) => strtolower(trim((string) ($promo->data_values->audience ?? ''))) === 'vip');
    $businessPromotions = \Illuminate\Support\Facades\Schema::hasTable('business_promotions')
        ? \App\Models\BusinessPromotion::where('status', 'active')->where('starts_at', '<=', now())->where('ends_at', '>', now())->latest('approved_at')->get()
        : collect();
    $newsContent = getContent('about_news.content', true)?->data_values;
    $news = getContent('about_news.element', orderById: true)->take(3);
@endphp

<section class="nocturne-promotions-hero">
    <div class="nocturne-promotions-hero__copy">
        <span class="nocturne-eyebrow"><i class="ph-fill ph-ticket"></i> @lang('Offers made for you')</span>
        <h1>{{ __($content->heading ?? 'Promotions') }}</h1>
        <p>{{ __($content->subheading ?? 'Discover player rewards, limited-time events and exclusive VIP offers.') }}</p>
        <a class="nocturne-promotions-hero__cta" href="#promotion-list">@lang('Explore promotions') <i class="ph-bold ph-arrow-down"></i></a>
    </div>
    <div class="nocturne-promotions-hero__art" aria-hidden="true">
        @if (!empty($content->hero_image))
            <img src="{{ frontendImage('promotions', $content->hero_image, '1400x500') }}" alt="">
        @else
            <span class="nocturne-promotions-hero__glow"></span>
            <i class="ph-fill ph-ticket nocturne-promotions-hero__ticket"></i>
            <i class="ph-fill ph-gift nocturne-promotions-hero__gift"></i>
            <span class="nocturne-promotions-hero__orb nocturne-promotions-hero__orb--one"></span>
            <span class="nocturne-promotions-hero__orb nocturne-promotions-hero__orb--two"></span>
        @endif
    </div>
</section>

<section class="nocturne-section nocturne-promotions" id="promotion-list">
    <div class="nocturne-section-heading">
        <span><i class="ph-fill ph-ticket"></i><h2>@lang('Promotions')</h2></span>
        <p>@lang('Choose an offer and see what is waiting for you.')</p>
    </div>

    <div class="nocturne-promotion-tabs" role="tablist" aria-label="@lang('Promotion category')">
        <button type="button" class="is-active" role="tab" aria-selected="true" data-promotion-tab="current">@lang('Current')</button>
        <button type="button" role="tab" aria-selected="false" data-promotion-tab="vip">@lang('For VIP')</button>
    </div>

    <div class="nocturne-promo-grid" data-promotion-panel="current" role="tabpanel">
        @forelse ($currentPromotions as $promo)
            <article class="nocturne-promo-card">
                <div class="nocturne-promo-card__image">
                    @if (!empty($promo->data_values->image))
                        <img src="{{ frontendImage('promotions', $promo->data_values->image, '500x300') }}" alt="{{ __($promo->data_values->title ?? 'Promotion') }}" loading="lazy">
                    @else
                        <span class="nocturne-promo-card__placeholder"><i class="ph-fill ph-sparkle"></i></span>
                    @endif
                </div>
                <div class="nocturne-promo-card__body">
                    <span class="nocturne-promo-card__tag"><i class="ph-fill ph-sparkle"></i> @lang('Featured offer')</span>
                    <h3>{{ __($promo->data_values->title ?? 'Promotion') }}</h3>
                    <p>{{ __($promo->data_values->description ?? '') }}</p>
                    @if (!empty($promo->data_values->button_url))
                        <a href="{{ $promo->data_values->button_url }}">@lang('Read More') <i class="ph-bold ph-arrow-right"></i></a>
                    @endif
                </div>
            </article>
        @empty
            <div class="nocturne-promotion-empty"><i class="ph-fill ph-ticket"></i><strong>@lang('New offers are on the way')</strong><span>@lang('Check back soon for the latest promotions.')</span></div>
        @endforelse
    </div>

    <div class="nocturne-promo-grid" data-promotion-panel="vip" role="tabpanel" hidden>
        @forelse ($vipPromotions as $promo)
            <article class="nocturne-promo-card">
                <div class="nocturne-promo-card__image">
                    @if (!empty($promo->data_values->image))
                        <img src="{{ frontendImage('promotions', $promo->data_values->image, '500x300') }}" alt="{{ __($promo->data_values->title ?? 'VIP promotion') }}" loading="lazy">
                    @else
                        <span class="nocturne-promo-card__placeholder"><i class="ph-fill ph-crown"></i></span>
                    @endif
                </div>
                <div class="nocturne-promo-card__body">
                    <span class="nocturne-promo-card__tag"><i class="ph-fill ph-crown"></i> @lang('VIP exclusive')</span>
                    <h3>{{ __($promo->data_values->title ?? 'VIP promotion') }}</h3>
                    <p>{{ __($promo->data_values->description ?? '') }}</p>
                    @if (!empty($promo->data_values->button_url))
                        <a href="{{ $promo->data_values->button_url }}">@lang('Read More') <i class="ph-bold ph-arrow-right"></i></a>
                    @endif
                </div>
            </article>
        @empty
            <div class="nocturne-promotion-empty"><i class="ph-fill ph-crown"></i><strong>@lang('VIP offers are on the way')</strong><span>@lang('Exclusive offers for VIP members will appear here.')</span></div>
        @endforelse
    </div>
</section>

@if ($businessPromotions->isNotEmpty())
    <section class="nocturne-section nocturne-business-spotlight">
        <div class="nocturne-section-heading"><span><i class="ph-fill ph-storefront"></i><h2>@lang('Winxpot Business Spotlight')</h2></span><p>@lang('Discover businesses promoted by our community.')</p></div>
        <div class="nocturne-promo-grid">
            @foreach ($businessPromotions as $businessPromotion)
                <a class="nocturne-promo-card nocturne-business-spotlight-card" href="{{ $businessPromotion->destination_url }}" target="_blank" rel="sponsored noopener noreferrer">
                    <div class="nocturne-promo-card__image"><img src="{{ getImage('assets/images/business-promotions/' . $businessPromotion->image) }}" alt="{{ __($businessPromotion->business_name) }}" loading="lazy"></div>
                    <div class="nocturne-promo-card__body"><span class="nocturne-promo-card__tag"><i class="ph-fill ph-megaphone-simple"></i> @lang('Community feature')</span><h3>{{ __($businessPromotion->title) }}</h3><p><strong>{{ __($businessPromotion->business_name) }}</strong> · {{ __($businessPromotion->description) }}</p><span class="nocturne-business-spotlight-link">@lang('Visit business') <i class="ph-bold ph-arrow-up-right"></i></span></div>
                </a>
            @endforeach
        </div>
    </section>
@endif

@if ($news->count())
    <section class="nocturne-about-section nocturne-promotions-news">
        <div class="nocturne-about-section-title nocturne-about-section-title--action">
            <span><i class="ph-fill ph-newspaper"></i><h2>{{ __($newsContent->heading ?? 'Last News') }}</h2></span>
            @if (!empty($newsContent->button_name))
                <a class="nocturne-about-view-all" href="{{ $newsContent->button_url ?: '#' }}">{{ __($newsContent->button_name) }} <i class="ph-bold ph-caret-right"></i></a>
            @endif
        </div>
        <div class="nocturne-about-news-grid">
            @foreach ($news as $item)
                <a class="nocturne-about-news-card" href="{{ $item->data_values->button_url ?: '#' }}">
                    <span class="nocturne-about-news-card__image">
                        @if (!empty($item->data_values->news_image))
                            <img src="{{ frontendImage('about_news', $item->data_values->news_image, '500x300') }}" alt="{{ __($item->data_values->title ?? 'News') }}" loading="lazy">
                        @else
                            <i class="ph-bold ph-image"></i><small>@lang('News image coming soon')</small>
                        @endif
                    </span>
                    <span class="nocturne-about-news-card__copy"><strong>{{ __($item->data_values->title) }}</strong><span>{{ __($item->data_values->description) }}</span><b>@lang('Read More') <i class="ph-bold ph-arrow-right"></i></b></span>
                </a>
            @endforeach
        </div>
    </section>
@endif

@push('script')
    <script>
        document.querySelectorAll('[data-promotion-tab]').forEach((tab) => {
            tab.addEventListener('click', () => {
                const selected = tab.dataset.promotionTab;
                document.querySelectorAll('[data-promotion-tab]').forEach((item) => {
                    const active = item === tab;
                    item.classList.toggle('is-active', active);
                    item.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                document.querySelectorAll('[data-promotion-panel]').forEach((panel) => {
                    panel.hidden = panel.dataset.promotionPanel !== selected;
                });
            });
        });
    </script>
@endpush
