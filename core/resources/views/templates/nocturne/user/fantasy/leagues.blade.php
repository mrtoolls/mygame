@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Leagues')</span></div>
        <div class="ff-header"><div><h1>@lang('Leagues')</h1></div></div>

        <div class="ff-league-forms">
            <form action="{{ route('user.fantasy.leagues.create') }}" method="POST" class="ff-league-form">
                @csrf
                <input type="text" name="name" placeholder="@lang('League name')" required maxlength="60">
                <select name="type"><option value="classic">@lang('Classic')</option><option value="h2h">@lang('Head-to-Head')</option></select>
                <button type="submit" class="btn btn--sm">@lang('Create')</button>
            </form>
            <form action="{{ route('user.fantasy.leagues.join') }}" method="POST" class="ff-league-form">
                @csrf
                <input type="text" name="code" placeholder="@lang('Join code')" required maxlength="10" style="text-transform:uppercase">
                <button type="submit" class="btn btn--sm">@lang('Join')</button>
            </form>
        </div>

        <h3 class="ff-section-title">@lang('Global Leaderboard')</h3>
        <div class="ff-standings">
            @foreach ($globalStandings as $i => $t)
                <div class="ff-standing-row {{ $t->id === $team->id ? 'is-me' : '' }}">
                    <span class="ff-standing-rank">{{ $i + 1 }}</span>
                    <span class="ff-standing-name">{{ $t->name }}</span>
                    <span class="ff-standing-points">{{ number_format($t->total_points) }} pts</span>
                </div>
            @endforeach
        </div>

        <h3 class="ff-section-title">@lang('Your Leagues')</h3>
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
.ff-league-forms { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
.ff-league-form { display: flex; gap: 6px; background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 8px; flex: 1; min-width: 220px; }
.ff-league-form input, .ff-league-form select { background: transparent; border: 0; color: #fff; font-size: 13px; flex: 1; min-width: 0; }
.ff-league-form .btn--sm { background: var(--nocturne-green, #00ef87); color: #073c2a; border: 0; border-radius: 8px; padding: 6px 12px; font-weight: 800; font-size: 12px; white-space: nowrap; }
.ff-standings { display: flex; flex-direction: column; gap: 4px; margin-bottom: 20px; }
.ff-standing-row { display: flex; align-items: center; gap: 12px; background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 8px 14px; font-size: 13px; color: #fff; }
.ff-standing-row.is-me { border-color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.06); }
.ff-standing-rank { width: 28px; font-weight: 800; color: var(--nocturne-muted, #929abc); }
.ff-standing-name { flex: 1; }
.ff-standing-points { font-weight: 800; color: var(--nocturne-green, #00ef87); }
.ff-league-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; }
.ff-league-card { display: grid; gap: 4px; background: #0d1023; border: 1px solid #2c3453; border-radius: 12px; padding: 14px; color: #fff; text-decoration: none; }
.ff-league-card span { color: var(--nocturne-muted, #929abc); font-size: 11px; text-transform: uppercase; }
.ff-league-card code { font-size: 11px; color: #ffd166; }
</style>
@endpush
