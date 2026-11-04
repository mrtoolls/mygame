@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Fixtures')</span></div>
        <div class="ff-header"><div><h1>@lang('Fixtures')</h1><p class="ff-sub">@lang('Gameweek') {{ $gameweek?->number }}</p></div></div>

        <div class="ff-gw-tabs">
            @foreach ($gameweeks as $gw)
                <a href="{{ route('user.fantasy.fixtures.index', ['gw' => $gw->number]) }}" class="ff-gw-tab {{ $gameweek && $gw->number === $gameweek->number ? 'is-active' : '' }} ff-gw-tab--{{ $gw->status }}">{{ $gw->number }}</a>
            @endforeach
        </div>

        <div class="ff-fixture-list">
            @forelse ($fixtures as $fixture)
                <a href="{{ route('user.fantasy.fixtures.show', $fixture->id) }}" class="ff-fixture-row ff-fixture-row--{{ $fixture->status }}">
                    <span class="ff-fixture-club"><x-fantasy-shirt :primary="$fixture->homeClub->color_primary" :secondary="$fixture->homeClub->color_secondary" :pattern="$fixture->homeClub->pattern" :size="26" />{{ $fixture->homeClub->short_code }}</span>
                    <span class="ff-fixture-score">
                        @if ($fixture->status === 'scheduled')
                            {{ $fixture->kickoff_at->format('D H:i') }}
                        @elseif ($fixture->status === 'live')
                            <span class="ff-live-dot"></span>{{ $fixture->home_score ?? 0 }}-{{ $fixture->away_score ?? 0 }}
                        @else
                            {{ $fixture->home_score }}-{{ $fixture->away_score }}
                        @endif
                    </span>
                    <span class="ff-fixture-club ff-fixture-club--away">{{ $fixture->awayClub->short_code }}<x-fantasy-shirt :primary="$fixture->awayClub->color_primary" :secondary="$fixture->awayClub->color_secondary" :pattern="$fixture->awayClub->pattern" :size="26" /></span>
                </a>
            @empty
                <p class="text-muted text-center">@lang('No fixtures found.')</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-gw-tabs { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 8px; margin-bottom: 16px; }
.ff-gw-tab { flex: 0 0 auto; width: 32px; height: 32px; display: grid; place-items: center; border-radius: 8px; background: #0d1023; border: 1px solid #2c3453; color: var(--nocturne-muted, #929abc); font-size: 12px; font-weight: 700; }
.ff-gw-tab.is-active { background: var(--nocturne-green, #00ef87); color: #073c2a; border-color: var(--nocturne-green, #00ef87); }
.ff-gw-tab--final:not(.is-active) { opacity: .6; }
.ff-fixture-list { display: flex; flex-direction: column; gap: 8px; }
.ff-fixture-row { display: flex; align-items: center; justify-content: space-between; background: #0d1023; border: 1px solid #2c3453; border-radius: 12px; padding: 12px 16px; color: #fff; text-decoration: none; }
.ff-fixture-club { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; flex: 1; }
.ff-fixture-club--away { justify-content: flex-end; }
.ff-fixture-score { font-size: 15px; font-weight: 800; min-width: 80px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 6px; }
.ff-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #ff5c7a; animation: ff-pulse 1.4s infinite; }
@keyframes ff-pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
@media (prefers-reduced-motion: reduce) { .ff-live-dot { animation: none; } }
</style>
@endpush
