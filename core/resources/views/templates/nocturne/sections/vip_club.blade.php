@php
    $content = getContent('vip_club.content', true);
    $tiers = getContent('vip_club.element', orderById: true);
@endphp
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="las la-crown"></i> {{ __(@$content->data_values->heading ?: 'VIP Club') }}</span>
        <p>{{ __(@$content->data_values->subheading) }}</p>
    </div>
</section>

@if ($tiers->count())
    <section class="nocturne-section">
        <div class="nocturne-tier-grid">
            @foreach ($tiers as $tier)
                <div class="nocturne-tier-card">
                    <img src="{{ getImage('assets/images/frontend/vip_club/' . @$tier->data_values->image, '80x80') }}" alt="@lang('image')">
                    <h5>{{ __(@$tier->data_values->title) }}</h5>
                    <p>{{ __(@$tier->data_values->description) }}</p>
                </div>
            @endforeach
        </div>
    </section>
@endif
