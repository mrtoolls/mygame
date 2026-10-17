@php
    $statistics = getContent('statistics.element', false, null, true);
@endphp
@if ($statistics->count())
    <section class="nocturne-section">
        <div class="nocturne-section__head">
            <h2>{{ gs('site_name') }} @lang('Statistics')</h2>
        </div>
        <div class="nocturne-stat-row">
            @foreach ($statistics as $statistic)
                <div class="nocturne-stat-card">
                    <strong>{{ __(@$statistic->data_values->amount) }}</strong>
                    <span>{{ __(@$statistic->data_values->title) }}</span>
                </div>
            @endforeach
        </div>
    </section>
@endif
