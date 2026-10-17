@php
    $content = getContent('promotions.content', true);
    $promotions = getContent('promotions.element', orderById: true);
@endphp
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="las la-ticket-alt"></i> {{ __(@$content->data_values->heading ?: 'Promotions') }}</span>
        <p>{{ __(@$content->data_values->subheading) }}</p>
    </div>
</section>

@if ($promotions->count())
    <section class="nocturne-section">
        <div class="nocturne-promo-grid">
            @foreach ($promotions as $promo)
                <div class="nocturne-promo-card">
                    <img src="{{ getImage('assets/images/frontend/promotions/' . @$promo->data_values->image, '500x300') }}" alt="@lang('image')">
                    <div class="nocturne-promo-card__body">
                        <h4>{{ __(@$promo->data_values->title) }}</h4>
                        <p>{{ __(@$promo->data_values->description) }}</p>
                        @if (@$promo->data_values->button_url)
                            <a href="{{ $promo->data_values->button_url }}">@lang('Read More') <i class="las la-arrow-right"></i></a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
