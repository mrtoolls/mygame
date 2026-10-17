@php
    $content = getContent('sponsorships.content', true);
    $sponsors = getContent('sponsorships.element', orderById: true);
@endphp
<section class="nocturne-page-hero">
    <div class="nocturne-page-hero__text">
        <span class="nocturne-eyebrow"><i class="las la-handshake"></i> {{ __(@$content->data_values->heading ?: 'Sponsorships') }}</span>
        <p>{{ __(@$content->data_values->subheading) }}</p>
    </div>
</section>

@if ($sponsors->count())
    <section class="nocturne-section">
        <div class="nocturne-sponsor-wrap">
            <div class="nocturne-sponsor-list" role="tablist">
                @foreach ($sponsors as $sponsor)
                    <button type="button" class="nocturne-sponsor-tab {{ $loop->first ? 'is-active' : '' }}" data-target="sponsor-{{ $sponsor->id }}">
                        <img src="{{ getImage('assets/images/frontend/sponsorships/' . @$sponsor->data_values->image, '160x160') }}" alt="@lang('image')">
                        {{ __(@$sponsor->data_values->title) }}
                    </button>
                @endforeach
            </div>
            <div class="nocturne-sponsor-detail">
                @foreach ($sponsors as $sponsor)
                    <div class="nocturne-sponsor-panel {{ $loop->first ? 'is-active' : '' }}" id="sponsor-{{ $sponsor->id }}">
                        <img src="{{ getImage('assets/images/frontend/sponsorships/' . @$sponsor->data_values->image, '160x160') }}" alt="@lang('image')">
                        <h4>{{ __(@$sponsor->data_values->title) }}</h4>
                        <p>{{ __(@$sponsor->data_values->description) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

@push('script')
<script>
    (() => {
        const tabs = document.querySelectorAll('.nocturne-sponsor-tab');
        tabs.forEach(tab => tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('is-active'));
            document.querySelectorAll('.nocturne-sponsor-panel').forEach(p => p.classList.remove('is-active'));
            tab.classList.add('is-active');
            document.getElementById(tab.dataset.target)?.classList.add('is-active');
        }));
    })();
</script>
@endpush
