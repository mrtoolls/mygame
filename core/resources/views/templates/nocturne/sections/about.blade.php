@php
    $content = getContent('about.content', true);
@endphp
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="las la-spade"></i> {{ __(@$content->data_values->heading ?: 'About Us') }}</span>
        <p>{{ __(@$content->data_values->description) }}</p>
    </div>
    @if (@$content->data_values->image)
        <div class="nocturne-page-hero__media">
            <img src="{{ getImage('assets/images/frontend/about/' . $content->data_values->image, '485x510') }}" alt="@lang('image')">
        </div>
    @endif
</section>
