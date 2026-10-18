@php
    $statsContent = getContent('about_statistics.content', true)?->data_values;
    $stats = getContent('about_statistics.element', orderById: true);
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title"><i class="ph-fill ph-chart-bar"></i><h2>{{ __($statsContent->heading ?? 'Winxpot Statistics') }}</h2></div>
    <div class="nocturne-about-stat-grid">
        @foreach($stats as $stat)<article class="nocturne-about-stat-card"><strong>{{ __($stat->data_values->amount) }}</strong><span>{{ __($stat->data_values->title) }}</span></article>@endforeach
    </div>
</section>
