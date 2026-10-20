@php
    $statsContent = getContent('about_statistics.content', true)?->data_values;
    $stats = getContent('about_statistics.element', orderById: true);
    $liveStatKey = fn ($title) => match (true) {
        str_contains(strtolower($title), 'online') => 'online',
        str_contains(strtolower($title), 'registered') => 'registered',
        str_contains(strtolower($title), 'games available') => 'games',
        default => null,
    };
@endphp
<section class="nocturne-about-section">
    <div class="nocturne-about-section-title"><i class="ph-fill ph-chart-bar"></i><h2>{{ __($statsContent->heading ?? 'Winxpot Statistics') }}</h2></div>
    <div class="nocturne-about-stat-grid">
        @foreach($stats as $stat)
            @php($liveKey = $liveStatKey($stat->data_values->title))
            <article class="nocturne-about-stat-card">
                <strong @if($liveKey) data-live-stat="{{ $liveKey }}" @endif>{{ __($stat->data_values->amount) }}</strong>
                <span>{{ __($stat->data_values->title) }}</span>
            </article>
        @endforeach
    </div>
</section>
@push('script')
<script>
"use strict";
(function () {
    var nodes = document.querySelectorAll('[data-live-stat]');
    if (!nodes.length) return;
    function trimZeros(str) {
        return str.indexOf('.') === -1 ? str : str.replace(/0+$/, '').replace(/\.$/, '');
    }
    function compact(value) {
        value = Number(value);
        if (value >= 1e6) return trimZeros((value / 1e6).toFixed(1)) + 'M';
        if (value >= 1e3) return trimZeros((value / 1e3).toFixed(1)) + 'K';
        return value.toLocaleString('en-US');
    }
    function paint(data) {
        nodes.forEach(function (node) {
            var key = node.dataset.liveStat;
            if (data[key] === undefined) return;
            var text = key === 'online' ? Number(data[key]).toLocaleString('en-US') : compact(data[key]);
            node.textContent = text + (key !== 'games' ? '+' : '');
        });
    }
    function poll() {
        fetch('{{ route('stats.live') }}', { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) { if (data) paint(data); })
            .catch(function () {});
    }
    poll();
    setInterval(poll, 20000);
})();
</script>
@endpush
