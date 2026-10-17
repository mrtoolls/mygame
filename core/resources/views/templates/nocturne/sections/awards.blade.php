@php
    $content = getContent('awards.content', true);
    $awards = getContent('awards.element', orderById: true);
@endphp
@if ($awards->count())
    <section class="nocturne-section">
        <div class="nocturne-section__head">
            <h2>{{ __(@$content->data_values->heading ?: gs('site_name') . ' Awards') }}</h2>
            <p>{{ __(@$content->data_values->subheading) }}</p>
        </div>
        <div class="nocturne-award-grid">
            @foreach ($awards as $award)
                <div class="nocturne-award-card">
                    <img src="{{ getImage('assets/images/frontend/awards/' . @$award->data_values->image, '140x140') }}" alt="@lang('image')">
                    <strong>{{ __(@$award->data_values->title) }}</strong>
                    <span>{{ __(@$award->data_values->description) }}</span>
                </div>
            @endforeach
        </div>
    </section>
@endif
