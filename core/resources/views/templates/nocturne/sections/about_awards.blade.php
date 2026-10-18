@php
    $awardsContent = getContent('about_awards.content', true)?->data_values;
    $awards = getContent('about_awards.element', orderById: true);
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title"><i class="ph-fill ph-medal"></i><h2>{{ __($awardsContent->heading ?? 'Awards') }}</h2></div>
    <div class="nocturne-about-awards-feature">
        <div class="nocturne-about-awards-feature__copy"><h3>{{ __(gs('site_name')) }} Awards</h3><p>{{ __($awardsContent->subheading ?? '') }}</p></div>
        <div class="nocturne-about-awards-feature__art">
            @if(!empty($awardsContent->feature_image))<img src="{{ frontendImage('about_awards', $awardsContent->feature_image, '1400x420') }}" alt="{{ __($awardsContent->heading ?? 'Award presentation') }}">@else<div class="nocturne-about-image-placeholder"><i class="ph-bold ph-image"></i><span>Awards feature image here</span></div>@endif
        </div>
    </div>
    <div class="nocturne-about-award-grid">
        @foreach($awards as $award)
            <article class="nocturne-about-award-card">
                <span>@if(!empty($award->data_values->award_image))<img src="{{ frontendImage('about_awards', $award->data_values->award_image, '140x140') }}" alt="">@else<i class="ph-bold ph-image"></i><small>Award image here</small>@endif</span>
                <strong>{{ __($award->data_values->title) }}</strong><small>{{ __($award->data_values->description) }}</small>
            </article>
        @endforeach
    </div>
</section>
