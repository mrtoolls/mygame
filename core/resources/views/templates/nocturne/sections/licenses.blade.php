@php
    $content = getContent('licenses.content', true);
    $licenses = getContent('licenses.element', orderById: true);
@endphp
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="ph-bold ph-shield-check"></i> {{ __(@$content->data_values->heading ?: 'Licenses and Security') }}</span>
        <p>{{ __(@$content->data_values->subheading) }}</p>
        @if (@$content->data_values->image)
            <a class="nocturne-outline-btn" href="{{ getImage('assets/images/frontend/licenses/' . $content->data_values->image, '800x1000') }}" target="_blank" rel="noopener">
                <i class="ph-bold ph-file-text"></i> @lang('Certificate of Cooperation')
            </a>
        @endif
    </div>
</section>

@if ($licenses->count())
    <section class="nocturne-section">
        <div class="nocturne-license-list">
            @foreach ($licenses as $license)
                <div class="nocturne-license-row">
                    <img src="{{ getImage('assets/images/frontend/licenses/' . @$license->data_values->image, '120x60') }}" alt="@lang('image')">
                    <div>
                        <strong>{{ __(@$license->data_values->title) }}</strong>
                        <p>{{ __(@$license->data_values->description) }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
