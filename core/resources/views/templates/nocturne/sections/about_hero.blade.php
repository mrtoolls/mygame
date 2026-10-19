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
<style>
@media(max-width:700px){
    .nocturne-about-hero{position:relative;display:flex!important;flex-direction:column;justify-content:flex-start;gap:0;min-height:0!important;height:auto!important;margin:16px 0 22px!important;padding:22px 18px 0!important;overflow:hidden;border-radius:22px!important;background:radial-gradient(ellipse at 50% 78%,rgba(0,232,139,.24),transparent 52%),linear-gradient(160deg,#171d35 0%,#151b30 70%,#083e3a 100%)!important;text-align:center}
    .nocturne-about-hero__copy{position:relative!important;z-index:2;width:100%;max-width:440px;margin:0 auto!important;padding:0!important;text-align:center!important}
    .nocturne-about-hero__copy .nocturne-about-kicker{display:flex;align-items:center;justify-content:center;gap:9px;margin:0 auto 10px!important;font-size:21px!important;line-height:1.25;font-weight:800!important;color:#f4f6ff!important}
    .nocturne-about-hero__copy .nocturne-about-kicker i{color:#00e88b!important;font-size:23px}
    .nocturne-about-hero__copy p{max-width:385px;margin:0 auto!important;color:#929cbd!important;font-size:15px!important;line-height:1.5!important;font-weight:600!important;text-wrap:pretty}
    .nocturne-about-hero__art{position:relative!important;z-index:1;display:block!important;width:calc(100% + 36px)!important;height:150px!important;min-height:150px!important;margin:16px -18px 0!important;overflow:hidden!important;flex:0 0 150px!important;background:linear-gradient(180deg,rgba(9,16,31,0),rgba(0,232,139,.12))!important}
    .nocturne-about-hero__art:before{position:absolute;z-index:1;inset:0;content:"";pointer-events:none;background:linear-gradient(180deg,#151b30 0%,rgba(21,27,48,.32) 16%,transparent 58%,rgba(0,232,139,.08) 100%)}
    .nocturne-about-hero__art img{display:block;width:100%!important;height:100%!important;max-height:none!important;object-fit:cover!important;object-position:center 22%!important}
    .nocturne-about-hero__art .nocturne-about-image-placeholder{height:100%;display:flex;align-items:center;justify-content:center;gap:7px;flex-direction:column;color:#8994b7;font-size:13px}
    .nocturne-about-hero__art .nocturne-about-image-placeholder small{display:none}
}
@media(max-width:380px){.nocturne-about-hero{padding:19px 15px 0!important}.nocturne-about-hero__copy p{font-size:14px!important}.nocturne-about-hero__art{width:calc(100% + 30px)!important;height:132px!important;min-height:132px!important;flex-basis:132px!important;margin:14px -15px 0!important}}
</style>
