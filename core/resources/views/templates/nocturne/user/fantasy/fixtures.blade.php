@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Fixtures')</span></div>
        <section class="ff-page-hero ff-fixtures-hero"><div><span class="ff-page-kicker"><i class="ph-fill ph-calendar-check"></i>@lang('MATCH CENTRE')</span><h1>@lang('Fixtures')</h1><p>@lang('Every kickoff, live score and final result for the current gameweek.')</p></div><div class="ff-current-gw"><small>@lang('SELECTED GAMEWEEK')</small><strong>{{ $gameweek?->number ?? '—' }}</strong><span>{{ $gameweek?->status ? ucfirst($gameweek->status) : __('No schedule') }}</span></div></section>

        <div class="ff-gw-tabs">
            @foreach ($gameweeks as $gw)
                <a href="{{ route('user.fantasy.fixtures.index', ['gw' => $gw->number]) }}" class="ff-gw-tab {{ $gameweek && $gw->number === $gameweek->number ? 'is-active' : '' }} ff-gw-tab--{{ $gw->status }}">{{ $gw->number }}</a>
            @endforeach
        </div>

        <div class="ff-fixture-list">
            @forelse ($fixtures as $fixture)
                <a href="{{ route('user.fantasy.fixtures.show', $fixture->id) }}" class="ff-fixture-row ff-fixture-row--{{ $fixture->status }}">
                    <span class="ff-fixture-club"><x-fantasy-shirt :primary="$fixture->homeClub->color_primary" :secondary="$fixture->homeClub->color_secondary" :pattern="$fixture->homeClub->pattern" :size="30" /><strong>{{ $fixture->homeClub->short_code }}</strong></span>
                    <span class="ff-fixture-center"><small class="ff-fixture-state ff-fixture-state--{{ $fixture->status }}">{{ __(ucfirst($fixture->status)) }}</small><span class="ff-fixture-score">
                        @if ($fixture->status === 'scheduled')
                            {{ $fixture->kickoff_at?->format('D, M j · H:i') ?? __('Time TBC') }}
                        @elseif ($fixture->status === 'live')
                            <span class="ff-live-dot"></span>{{ $fixture->home_score ?? 0 }}-{{ $fixture->away_score ?? 0 }}
                        @else
                            {{ $fixture->home_score ?? 0 }}-{{ $fixture->away_score ?? 0 }}
                        @endif
                    </span></span>
                    <span class="ff-fixture-club ff-fixture-club--away"><strong>{{ $fixture->awayClub->short_code }}</strong><x-fantasy-shirt :primary="$fixture->awayClub->color_primary" :secondary="$fixture->awayClub->color_secondary" :pattern="$fixture->awayClub->pattern" :size="30" /></span>
                    <i class="ff-fixture-arrow ph-bold ph-caret-right" aria-hidden="true"></i>
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
.ff-page-hero { position:relative; isolation:isolate; display:flex; align-items:center; justify-content:space-between; gap:18px; overflow:hidden; padding:23px 24px; margin-bottom:15px; border:1px solid #252d4b; border-radius:20px; background:radial-gradient(ellipse at 90% 0,rgba(0,239,135,.13),transparent 38%),linear-gradient(135deg,#171d38,#10152a 70%,#101d2b); }
.ff-page-hero h1 { margin:0 0 5px; color:#f5f6ff; font-size:clamp(22px,2.2vw,28px); letter-spacing:-.035em; line-height:1.15; font-weight:750; }
.ff-page-hero p { margin:0; color:#9aa5c9; font-size:13px; line-height:1.5; }
.ff-page-kicker { display:inline-flex; gap:6px; align-items:center; margin-bottom:7px; color:#00ef87; font-size:9px; font-weight:800; letter-spacing:.12em; }
.ff-current-gw { display:grid; justify-items:center; min-width:110px; padding:10px 15px; border:1px solid rgba(0,239,135,.22); border-radius:14px; background:rgba(8,14,29,.5); }
.ff-current-gw small { color:#8994b9; font-size:8px; letter-spacing:.08em; }
.ff-current-gw strong { color:#00ef87; font-size:23px; line-height:1.2; }
.ff-current-gw span { color:#aeb8d2; font-size:10px; }
.ff-gw-tabs { display:flex; gap:6px; overflow-x:auto; padding:2px 1px 9px; margin-bottom:14px; scrollbar-width:none; -webkit-overflow-scrolling:touch; }
.ff-gw-tabs::-webkit-scrollbar { display:none; }
.ff-gw-tab { flex:0 0 auto; min-width:39px; height:37px; display:grid; place-items:center; border-radius:11px; background:#11162c; border:1px solid #2c3453; color:#929abc; font-size:11px; font-weight:700; transition:background .15s,border-color .15s; }
.ff-gw-tab.is-active { background: var(--nocturne-green, #00ef87); color: #073c2a; border-color: var(--nocturne-green, #00ef87); }
.ff-gw-tab--final:not(.is-active) { opacity: .6; }
.ff-fixture-list { display:grid; gap:8px; }
.ff-fixture-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(105px,.72fr) minmax(0,1fr) 16px; align-items:center; gap:12px; min-height:72px; padding:11px 15px; color:#fff; text-decoration:none; border:1px solid #252d4b; border-radius:15px; background:linear-gradient(110deg,#151b34,#101529); transition:border-color .15s,transform .15s; }
.ff-fixture-row:hover { color:#fff; border-color:#3b466c; transform:translateY(-1px); }
.ff-fixture-club { display:flex; align-items:center; gap:9px; min-width:0; font-size:13px; color:#f4f5ff; }
.ff-fixture-club strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:700; }
.ff-fixture-club--away { justify-content:flex-end; }
.ff-fixture-center { display:grid; justify-items:center; gap:4px; min-width:0; }
.ff-fixture-state { color:#8994b9; font-size:8px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
.ff-fixture-state--live { color:#ff647e; }
.ff-fixture-state--final { color:#929abc; }
.ff-fixture-score { display:flex; align-items:center; justify-content:center; gap:6px; min-width:0; color:#f3f5ff; font-size:14px; font-weight:750; font-variant-numeric:tabular-nums; text-align:center; white-space:nowrap; }
.ff-fixture-row--live { border-color:rgba(255,92,122,.32); background:linear-gradient(110deg,rgba(255,92,122,.07),#11162c 55%); }
.ff-fixture-arrow { color:#7682a9; font-size:12px; }
.ff-live-dot { width: 7px; height: 7px; border-radius: 50%; background: #ff5c7a; animation: ff-pulse 1.4s infinite; }
@keyframes ff-pulse { 0%,100% { opacity: 1; } 50% { opacity: .3; } }
@media (prefers-reduced-motion: reduce) { .ff-live-dot { animation: none; } }
@media(max-width:575px) {
 .ff-page-hero { padding:18px 15px; border-radius:17px; }
 .ff-page-hero p { max-width:235px; font-size:11px; }
 .ff-current-gw { min-width:82px; padding:8px; }
 .ff-current-gw small { font-size:7px; }
 .ff-current-gw strong { font-size:20px; }
 .ff-gw-tabs { gap:5px; margin-right:-2px; }
 .ff-gw-tab { min-width:36px; height:35px; }
 .ff-fixture-row { grid-template-columns:minmax(0,1fr) 82px minmax(0,1fr); gap:5px; min-height:65px; padding:9px 8px; border-radius:13px; }
 .ff-fixture-club { gap:5px; font-size:11px; }
 .ff-fixture-club x-fantasy-shirt { transform:scale(.85); }
 .ff-fixture-score { font-size:12px; }
 .ff-fixture-state { font-size:7px; }
 .ff-fixture-arrow { display:none; }
}
</style>
@endpush
