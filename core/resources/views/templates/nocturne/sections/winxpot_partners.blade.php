@php
    $partners = getContent('footer_partners.element', false, null, true);
@endphp
<section class="nocturne-community-section nocturne-partners-section">
    <div class="nocturne-community-heading"><div><span class="nocturne-community-kicker">@lang('BUILT TOGETHER')</span><h2><i class="ph-fill ph-handshake"></i>@lang('Winxpot Partners')</h2><p>@lang('Meet the partners who help shape the Winxpot experience.')</p></div></div>
    <div class="nocturne-partners-rail">
        @forelse($partners as $partner)
            <article class="nocturne-partner-card">
                <span class="nocturne-partner-card__logo">
                    @if(!empty($partner->data_values->image))<img src="{{ frontendImage('footer_partners', $partner->data_values->image, '240x120') }}" alt="{{ __($partner->data_values->partner_name ?: 'Winxpot partner') }}" loading="lazy">
                    @else<i class="ph-fill ph-image"></i><small>@lang('Partner logo here')</small>@endif
                </span>
                <span><strong>{{ __($partner->data_values->partner_name ?: 'Winxpot Partner') }}</strong><small>@lang('Official Winxpot partner')</small></span>
                <i class="ph-bold ph-arrow-up-right"></i>
            </article>
        @empty
            @for($slot = 0; $slot < 4; $slot++)
                <article class="nocturne-partner-card nocturne-partner-card--placeholder"><span class="nocturne-partner-card__logo"><i class="ph-fill ph-image"></i><small>@lang('Partner logo here')</small></span><span><strong>@lang('Partner name here')</strong><small>@lang('Upload logos in Admin → Frontend → Manage Sections → Footer Partners')</small></span><i class="ph-bold ph-arrow-up-right"></i></article>
            @endfor
        @endforelse
    </div>
</section>
