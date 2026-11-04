@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Contests')</span></div>
        <section class="ff-page-hero ff-contests-hero"><div><span class="ff-page-kicker"><i class="ph-fill ph-coins"></i>@lang('FANTASY CONTESTS')</span><h1>@lang('Play for the prize pool')</h1><p>@lang('Join a gameweek contest, climb the standings and compete for coin rewards.')</p></div><div class="ff-contest-gw"><small>@lang('CURRENT GAMEWEEK')</small><strong>{{ $gameweek?->number ?? '—' }}</strong></div></section>

        <div class="ff-contest-grid">
            @forelse ($contests as $contest)
                <div class="ff-contest-card ff-contest-card--{{ $contest->type }}">
                    <div class="ff-contest-card__head">
                        <strong>{{ $contest->name }}</strong>
                        <span class="ff-contest-tag">{{ $contest->type === 'h2h' ? __('Head-to-Head') : ($contest->type === 'sponsored' ? __('Sponsored') : __('Gameweek Pool')) }}</span>
                    </div>
                    <div class="ff-contest-card__body">
                        @if ($contest->type === 'sponsored')
                            <span><small>@lang('Prize pool')</small><strong><i class="ph-fill ph-coin"></i>{{ number_format($contest->sponsored_pool, 0) }} @lang('coins')</strong></span>
                        @else
                            <span><small>@lang('Entry fee')</small><strong><i class="ph-fill ph-coin"></i>{{ number_format($contest->entry_fee, 0) }} @lang('coins')</strong></span>
                        @endif
                        <span class="ff-contest-entrants"><small>@lang('Players entered')</small><strong>{{ number_format($entryCounts[$contest->id] ?? 0) }}{{ $contest->max_entrants ? ' / ' . number_format($contest->max_entrants) : '' }}</strong></span>
                    </div>
                    @if (isset($myEntries[$contest->id]))
                        <span class="ff-contest-entered"><i class="ph-fill ph-check-circle"></i>@lang('Entered')</span>
                    @elseif ($contest->status === 'open')
                        <form action="{{ route('user.fantasy.contests.enter', $contest->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn--gradient w-100">@lang('Enter')</button>
                        </form>
                    @else
                        <span class="ff-contest-closed">@lang('Entries closed')</span>
                    @endif
                </div>
            @empty
                <p class="text-muted">@lang('No contests open for this gameweek yet.')</p>
            @endforelse
        </div>

        @if ($myRecentEntries->isNotEmpty())
            <h3 class="ff-section-title">@lang('Your Recent Entries')</h3>
            <div class="ff-standings">
                @foreach ($myRecentEntries as $entry)
                    <div class="ff-standing-row">
                        <span class="ff-standing-name">{{ $entry->contest->name }}</span>
                        <span>{{ ucfirst($entry->status) }}</span>
                        <span class="ff-standing-points">@if($entry->payout !== null)<i class="ph-fill ph-coin"></i> +{{ number_format($entry->payout, 0) }}@else — @endif</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('style')
<style>
.ff-page-hero { position:relative; isolation:isolate; display:flex; align-items:center; justify-content:space-between; gap:18px; overflow:hidden; padding:23px 24px; margin-bottom:15px; border:1px solid #252d4b; border-radius:20px; background:radial-gradient(ellipse at 90% 0,rgba(0,239,135,.13),transparent 38%),linear-gradient(135deg,#171d38,#10152a 70%,#101d2b); }
.ff-page-hero h1 { margin:0 0 5px; color:#f5f6ff; font-size:clamp(22px,2.2vw,28px); letter-spacing:-.035em; line-height:1.15; font-weight:750; }
.ff-page-hero p { margin:0; color:#9aa5c9; font-size:13px; line-height:1.5; }
.ff-page-kicker { display:inline-flex; gap:6px; align-items:center; margin-bottom:7px; color:#00ef87; font-size:9px; font-weight:800; letter-spacing:.12em; }
.ff-contest-gw { display:grid; justify-items:center; min-width:120px; padding:10px 14px; border:1px solid rgba(0,239,135,.22); border-radius:14px; background:rgba(8,14,29,.5); }
.ff-contest-gw small { color:#8994b9; font-size:8px; letter-spacing:.08em; }
.ff-contest-gw strong { color:#00ef87; font-size:24px; line-height:1.2; }
.ff-contest-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(235px,1fr)); gap:10px; margin-bottom:20px; }
.ff-contest-card { position:relative; align-content:start; gap:13px; min-width:0; padding:15px; border:1px solid #252d4b; border-radius:16px; background:radial-gradient(ellipse at 100% 0,rgba(0,239,135,.07),transparent 45%),linear-gradient(145deg,#151b34,#101529); }
.ff-contest-card--sponsored { border-color:rgba(255,209,102,.28); background:radial-gradient(ellipse at 100% 0,rgba(255,209,102,.1),transparent 45%),linear-gradient(145deg,#191b35,#101529); }
.ff-contest-card__head { display:grid; gap:9px; color:#f4f5ff; }
.ff-contest-card__head > strong { overflow-wrap:anywhere; font-size:14px; font-weight:700; line-height:1.35; }
.ff-contest-tag { width:max-content; max-width:100%; padding:4px 8px; border:1px solid rgba(0,239,135,.25); border-radius:999px; color:#00ef87; background:rgba(0,239,135,.07); font-size:8px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; }
.ff-contest-card--sponsored .ff-contest-tag { color:#ffd166; border-color:rgba(255,209,102,.25); background:rgba(255,209,102,.07); }
.ff-contest-card__body { display:grid; grid-template-columns:1fr 1fr; gap:8px; padding:11px 0; border-top:1px solid rgba(135,151,198,.13); border-bottom:1px solid rgba(135,151,198,.13); color:#9ba6c9; font-size:11px; }
.ff-contest-card__body > span { display:grid; gap:4px; min-width:0; }
.ff-contest-card__body small { color:#8994b9; font-size:9px; }
.ff-contest-card__body strong { display:flex; align-items:center; gap:4px; color:#f2f4ff; font-size:12px; font-weight:700; white-space:nowrap; }
.ff-contest-card__body strong i,.ff-standing-points i { color:#00ef87; }
.ff-contest-entrants { grid-column:1/-1; }
.ff-contest-card form { margin:0; }
.ff-contest-card .btn--gradient { min-height:43px; border-radius:11px; font-size:12px; font-weight:700; }
.ff-contest-entered,.ff-contest-closed { display:flex; align-items:center; gap:6px; min-height:38px; color:#00ef87; font-size:11px; font-weight:650; }
.ff-contest-closed { color:#929cbd; }
.ff-section-title { color:#f5f6ff; font-size:16px; font-weight:700; margin:22px 0 10px; }
.ff-standings { display:grid; gap:6px; }
.ff-standing-row { display:flex; align-items:center; gap:10px; min-height:46px; padding:9px 12px; border:1px solid #252d4b; border-radius:12px; color:#eff1fa; background:linear-gradient(110deg,#151b34,#101529); font-size:11px; }
.ff-standing-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ff-standing-points { color:#00ef87; font-weight:750; white-space:nowrap; }
@media(max-width:575px) { .ff-page-hero { padding:18px 15px; border-radius:17px; }.ff-page-hero p { max-width:250px; font-size:11px; }.ff-contest-gw { min-width:88px; padding:8px; }.ff-contest-gw small { font-size:7px; }.ff-contest-gw strong { font-size:21px; }.ff-contest-grid { grid-template-columns:1fr; gap:8px; }.ff-contest-card { padding:13px; border-radius:14px; }.ff-contest-card__body { padding:9px 0; }.ff-contest-card__body strong { font-size:11px; } }
</style>
@endpush
