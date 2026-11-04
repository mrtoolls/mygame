@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Leagues')</span></div>
        <section class="ff-page-hero ff-leagues-hero"><div><span class="ff-page-kicker"><i class="ph-fill ph-trophy"></i>@lang('PLAY TOGETHER')</span><h1>@lang('Your leagues')</h1><p>@lang('Build a private league, invite friends and follow every place on the table.')</p></div><span class="ff-league-hero-icon"><i class="ph-fill ph-users-three"></i></span></section>

        <div class="ff-league-forms">
            <form action="{{ route('user.fantasy.leagues.create') }}" method="POST" class="ff-league-form">
                @csrf
                <div class="ff-league-form__copy"><i class="ph-fill ph-plus-circle"></i><span><strong>@lang('Create a league')</strong><small>@lang('Start a private table for your friends')</small></span></div>
                <input type="text" name="name" placeholder="@lang('League name')" required maxlength="60">
                <select name="type"><option value="classic">@lang('Classic')</option><option value="h2h">@lang('Head-to-Head')</option></select>
                <button type="submit" class="btn btn--sm"><i class="ph-bold ph-plus"></i>@lang('Create')</button>
            </form>
            <form action="{{ route('user.fantasy.leagues.join') }}" method="POST" class="ff-league-form">
                @csrf
                <div class="ff-league-form__copy"><i class="ph-fill ph-key"></i><span><strong>@lang('Join a league')</strong><small>@lang('Enter an invite code to join')</small></span></div>
                <input type="text" name="code" placeholder="@lang('Join code')" required maxlength="10" style="text-transform:uppercase">
                <button type="submit" class="btn btn--sm"><i class="ph-bold ph-arrow-right"></i>@lang('Join')</button>
            </form>
        </div>

        <div class="ff-section-heading"><div><span class="ff-page-kicker">@lang('SEASON RANKINGS')</span><h2>@lang('Global leaderboard')</h2></div><span class="ff-section-note">@lang('Top managers')</span></div>
        <div class="ff-standings">
            @foreach ($globalStandings as $i => $t)
                <div class="ff-standing-row {{ $t->id === $team->id ? 'is-me' : '' }}">
                    <span class="ff-standing-rank">{{ $i + 1 }}</span>
                    <span class="ff-standing-name">{{ $t->name }}</span>
                    <span class="ff-standing-points">{{ number_format($t->total_points) }} pts</span>
                </div>
            @endforeach
            @if ($globalStandings->isEmpty())<div class="ff-league-empty"><i class="ph-fill ph-chart-bar"></i><span>@lang('The leaderboard will appear once teams start earning points.')</span></div>@endif
        </div>

        <div class="ff-section-heading"><div><span class="ff-page-kicker">@lang('YOUR GROUPS')</span><h2>@lang('Your leagues')</h2></div></div>
        <div class="ff-league-cards">
            @forelse ($memberships as $m)
                <a href="{{ route('user.fantasy.leagues.show', $m->league_id) }}" class="ff-league-card">
                    <strong>{{ $m->league->name }}</strong>
                    <span>{{ ucfirst($m->league->type) }}</span>
                    @if ($m->league->join_code)<code>{{ $m->league->join_code }}</code>@endif
                </a>
            @empty
                <p class="text-muted">@lang('You have not joined any private leagues yet.')</p>
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
.ff-league-hero-icon { display:grid; place-items:center; flex:0 0 58px; width:58px; height:58px; border:1px solid rgba(0,239,135,.23); border-radius:18px; color:#00ef87; background:rgba(0,239,135,.09); font-size:25px; }
.ff-league-forms { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:11px; margin-bottom:22px; }
.ff-league-form { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; padding:14px; min-width:0; border:1px solid #252d4b; border-radius:15px; background:linear-gradient(145deg,#151b34,#101529); }
.ff-league-form__copy { display:flex; grid-column:1/-1; align-items:center; gap:9px; margin-bottom:3px; }
.ff-league-form__copy > i { display:grid; place-items:center; width:33px; height:33px; border-radius:10px; color:#00ef87; background:rgba(0,239,135,.09); font-size:17px; }
.ff-league-form__copy span { display:grid; gap:2px; }
.ff-league-form__copy strong { color:#f4f5ff; font-size:12px; font-weight:700; }
.ff-league-form__copy small { color:#8994b9; font-size:10px; }
.ff-league-form input,.ff-league-form select { width:100%; min-width:0; height:40px; padding:0 11px; border:1px solid #2a3352; border-radius:10px; background:#0d1227; color:#eff1fa; font-size:12px; }
.ff-league-form select option { color:#111; }
.ff-league-form .btn--sm { display:inline-flex; align-items:center; justify-content:center; gap:5px; min-height:40px; background:#00df80; color:#062b20; border:0; border-radius:10px; padding:7px 13px; font-weight:700; font-size:11px; white-space:nowrap; }
.ff-section-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:12px; margin:20px 0 10px; }
.ff-section-heading h2 { margin:0; color:#f5f6ff; font-size:17px; font-weight:700; letter-spacing:-.02em; }
.ff-section-heading .ff-page-kicker { margin:0 0 4px; }
.ff-section-note { color:#8994b9; font-size:10px; }
.ff-standings { display:flex; flex-direction:column; gap:6px; margin-bottom:20px; }
.ff-standing-row { display:flex; align-items:center; gap:12px; min-height:48px; background:linear-gradient(110deg,#151b34,#101529); border:1px solid #252d4b; border-radius:12px; padding:8px 13px; font-size:12px; color:#fff; }
.ff-standing-row.is-me { border-color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.06); }
.ff-standing-rank { display:grid; place-items:center; width:29px; height:29px; border-radius:9px; font-weight:750; color:#aeb8d2; background:#0d1227; }
.ff-standing-row:nth-child(1) .ff-standing-rank { color:#ffd166; }.ff-standing-row:nth-child(2) .ff-standing-rank { color:#d9e3fa; }.ff-standing-row:nth-child(3) .ff-standing-rank { color:#e9a97c; }
.ff-standing-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; }
.ff-standing-points { flex:0 0 auto; font-weight:750; color:#00ef87; white-space:nowrap; }
.ff-league-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:9px; }
.ff-league-card { position:relative; display:grid; gap:7px; min-width:0; border:1px solid #252d4b; border-radius:14px; padding:14px; color:#fff; text-decoration:none; background:linear-gradient(145deg,#151b34,#101529); transition:border-color .15s,transform .15s; }
.ff-league-card:hover { color:#fff; border-color:#3b466c; transform:translateY(-1px); }
.ff-league-card strong { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:13px; font-weight:700; }
.ff-league-card span { color:#9ba6c9; font-size:9px; letter-spacing:.08em; text-transform:uppercase; }
.ff-league-card code { width:max-content; max-width:100%; overflow:hidden; padding:4px 7px; border-radius:7px; color:#ffd166; background:rgba(255,209,102,.08); font-size:10px; text-overflow:ellipsis; }
.ff-league-empty { display:flex; align-items:center; gap:10px; padding:14px; border:1px dashed #303958; border-radius:12px; color:#8994b9; font-size:11px; }
.ff-league-empty i { color:#00ef87; font-size:18px; }
@media(max-width:700px) { .ff-page-hero { padding:18px 15px; border-radius:17px; }.ff-page-hero p { max-width:270px; font-size:11px; }.ff-league-forms { grid-template-columns:1fr; gap:8px; }.ff-league-form { padding:12px; }.ff-section-heading h2 { font-size:15px; }.ff-league-cards { grid-template-columns:repeat(2,minmax(0,1fr)); gap:7px; }.ff-league-card { padding:11px; } }
@media(max-width:360px) { .ff-league-cards { grid-template-columns:1fr; }.ff-league-form { grid-template-columns:1fr auto; }.ff-league-form select { grid-column:1/-1; grid-row:3; } }
</style>
@endpush
