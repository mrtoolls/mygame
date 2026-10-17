@php
    $bannerPromos = getContent('promotions.element', orderById: true)->take(3);
@endphp
@if ($bannerPromos->count())
    <div class="nocturne-promo-banners">
        @foreach ($bannerPromos as $promo)
            <div class="nocturne-promo-banner">
                <div class="nocturne-promo-banner__body">
                    <span class="nocturne-promo-banner__badge">@lang('Promotion')</span>
                    <h4>{{ __(@$promo->data_values->title) }}</h4>
                    <p>{{ \Illuminate\Support\Str::limit(strip_tags(__(@$promo->data_values->description)), 70) }}</p>
                    <a href="{{ @$promo->data_values->button_url ?: route('pages', 'promotions') }}">@lang('Read More')</a>
                </div>
                @if (@$promo->data_values->image)
                    <div class="nocturne-promo-banner__media">
                        <img src="{{ getImage('assets/images/frontend/promotions/' . $promo->data_values->image, '500x300') }}" alt="{{ __(@$promo->data_values->title) }}">
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
