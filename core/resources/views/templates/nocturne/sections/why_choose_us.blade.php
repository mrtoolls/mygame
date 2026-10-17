@php
    $chooseContent = getContent('why_choose_us.content', true);
    $chooseElements = getContent('why_choose_us.element', orderById: true);
@endphp
<section class="nocturne-section">
    <div class="nocturne-section__head">
        <h2>{{ __(@$chooseContent->data_values->heading ?: 'About ' . gs('site_name')) }}</h2>
        <p>{{ __(@$chooseContent->data_values->subheading) }}</p>
    </div>
    @if ($chooseElements->count())
        <div class="nocturne-feature-grid">
            @foreach ($chooseElements as $item)
                <div class="nocturne-feature-card">
                    <span class="nocturne-feature-card__icon">
                        <img src="{{ getImage('assets/images/frontend/why_choose_us/' . @$item->data_values->image, '35x35') }}" alt="@lang('image')">
                    </span>
                    <h5>{{ __(@$item->data_values->title) }}</h5>
                    <p>{{ __(@$item->data_values->description) }}</p>
                </div>
            @endforeach
        </div>
    @endif
</section>
