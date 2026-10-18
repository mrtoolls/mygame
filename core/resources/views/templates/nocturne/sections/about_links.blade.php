@php
    $linksContent = getContent('about_links.content', true)?->data_values;
    $links = getContent('about_links.element', orderById: true);
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title"><i class="ph-fill ph-squares-four"></i><h2>{{ __($linksContent->heading ?? 'Other Pages') }}</h2></div>
    <nav class="nocturne-about-links" aria-label="Related pages">
        @foreach($links as $link)<a href="{{ $link->data_values->button_url ?: '#' }}"><span>{{ __($link->data_values->title) }}</span><i class="ph-bold ph-caret-right"></i></a>@endforeach
    </nav>
</section>
