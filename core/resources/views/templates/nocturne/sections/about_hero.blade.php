@php $aboutHero = getContent('about_hero.content', true)?->data_values; @endphp
<section class="nocturne-about-hero nocturne-about-section">
    <div class="nocturne-about-hero__copy">
        <span class="nocturne-about-kicker"><i class="ph-bold ph-spade"></i> {{ __($aboutHero->heading ?? 'About Us') }}</span>
        <p>{{ __($aboutHero->description ?? '') }}</p>
    </div>
    <div class="nocturne-about-hero__art">
        @if(!empty($aboutHero->hero_image))
            <img src="{{ frontendImage('about_hero', $aboutHero->hero_image, '1400x450') }}" alt="{{ __($aboutHero->heading ?? 'About Winxpot') }}">
        @else
            <div class="nocturne-about-image-placeholder"><i class="ph-bold ph-image"></i><span>Hero image here</span><small>Upload the 3D hero artwork in Admin → Frontend → Manage Pages</small></div>
        @endif
    </div>
</section>
