@php
    $team = getContent('about_team.content', true)?->data_values;
    $teamGroups = getContent('about_team.element', orderById: true);
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title"><i class="ph-fill ph-users-three"></i><h2>{{ __($team->heading ?? 'Our Team') }}</h2></div>
    <div class="nocturne-about-team">
        <div class="nocturne-about-team__copy"><h3>{{ __($team->subheading ?? 'Our Team is Here for You') }}</h3><p>{{ __($team->description ?? '') }}</p></div>
        <div class="nocturne-about-team__map">
            @if(!empty($team->map_image))<img class="nocturne-about-team__map-art" src="{{ frontendImage('about_team', $team->map_image, '1400x460') }}" alt="">@else<div class="nocturne-about-image-placeholder"><i class="ph-bold ph-globe-hemisphere-west"></i><span>Team map image here</span></div>@endif
            @foreach($teamGroups as $group)
                <div class="nocturne-about-team-pin">
                    <span>@if(!empty($group->data_values->team_image))<img src="{{ frontendImage('about_team', $group->data_values->team_image, '180x180') }}" alt="">@else<i class="ph-fill ph-users-three"></i>@endif</span>
                    <strong>{{ __($group->data_values->title) }}</strong><small>{{ __($group->data_values->description) }}</small>
                </div>
            @endforeach
        </div>
    </div>
</section>
