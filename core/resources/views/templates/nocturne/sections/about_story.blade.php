@php
    $story = getContent('about_story.content', true)?->data_values;
    $features = getContent('about_story.element', orderById: true);
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title"><i class="ph-fill ph-notebook"></i><h2>{{ __($story->heading ?? 'About Winxpot') }}</h2></div>
    <div class="nocturne-about-story">
        <div class="nocturne-about-story__copy">
            <h3><span>{{ __(gs('site_name')) }}</span> <b>—</b> {{ __($story->subheading ?? '') }}</h3>
            <div class="nocturne-about-prose">{!! nl2br(e(__($story->description ?? ''))) !!}</div>
            @if(!empty($story->expanded_description))
                <div class="nocturne-about-prose nocturne-about-story__more" hidden>{!! nl2br(e(__($story->expanded_description))) !!}</div>
                <button type="button" class="nocturne-about-readmore" aria-expanded="false"><i class="ph-fill ph-caret-circle-down"></i><span>{{ __($story->read_more_label ?? 'Read More') }}</span></button>
            @endif
        </div>
        <div class="nocturne-about-feature-grid">
            @foreach($features as $feature)
                <article class="nocturne-about-feature-card">
                    <span class="nocturne-about-feature-card__icon">
                        @if(!empty($feature->data_values->icon_image))<img src="{{ frontendImage('about_story', $feature->data_values->icon_image, '96x96') }}" alt="">@else<i class="ph-fill ph-image"></i><small>Icon here</small>@endif
                    </span>
                    <div><h3>{{ __($feature->data_values->title) }}</h3><p>{{ __($feature->data_values->description) }}</p></div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@push('script')
<script>
document.querySelectorAll('.nocturne-about-readmore').forEach((button) => button.addEventListener('click', () => {
    const extra = button.previousElementSibling;
    const expanded = button.getAttribute('aria-expanded') === 'true';
    extra.hidden = expanded;
    button.setAttribute('aria-expanded', String(!expanded));
    button.querySelector('span').textContent = expanded ? @json(__($story->read_more_label ?? 'Read More')) : @json(__($story->read_less_label ?? 'Show Less'));
    button.querySelector('i').className = expanded ? 'ph-fill ph-caret-circle-down' : 'ph-fill ph-caret-circle-up';
}));
</script>
@endpush
