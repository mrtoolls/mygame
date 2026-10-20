@php
    $privilegeContent = getContent('vip_member_privileges.content', true)?->data_values;
    $privileges = getContent('vip_member_privileges.element', orderById: true);
@endphp
@if($privileges->isNotEmpty())
    <section class="nocturne-section nocturne-vip-perks" aria-labelledby="vip-perks-title">
        <div class="nocturne-vip-section-head"><div><span class="nocturne-vip-kicker">@lang('MADE FOR OUR MEMBERS')</span><h2 id="vip-perks-title">{{ __($privilegeContent->heading ?? 'VIP Club Privileges') }}</h2><p>@lang('A closer look at the advantages you can unlock as you climb.')</p></div></div>
        <div class="nocturne-vip-perk-grid">
            @foreach($privileges as $perk)
                <article class="nocturne-vip-perk-card">
                    <span class="nocturne-vip-perk-card__art">@if(!empty($perk->data_values->icon_image))<img src="{{ frontendImage('vip_member_privileges', $perk->data_values->icon_image, '180x180') }}" alt="">@else<i class="ph-fill ph-image"></i><small>@lang('Benefit image here')</small>@endif</span>
                    <div><h3>{{ __($perk->data_values->title) }}</h3><p>{{ __($perk->data_values->description) }}</p></div>
                    <i class="ph-bold ph-arrow-up-right nocturne-vip-perk-card__arrow"></i>
                </article>
            @endforeach
        </div>
    </section>
@endif
