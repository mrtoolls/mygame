@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><a href="{{ route('user.fantasy.leagues.index') }}">@lang('Leagues')</a><i class="ph-bold ph-caret-right"></i><span>{{ $league->name }}</span></div>
        <div class="ff-header"><div><h1>{{ $league->name }}</h1><p class="ff-sub">{{ ucfirst($league->type) }} @if ($league->join_code) &middot; @lang('Code'): <code>{{ $league->join_code }}</code> @endif</p></div></div>

        <div class="ff-standings">
            @foreach ($members as $i => $t)
                <div class="ff-standing-row"><span class="ff-standing-rank">{{ $i + 1 }}</span><span class="ff-standing-name">{{ $t->name }}</span><span class="ff-standing-points">{{ number_format($t->total_points) }} pts</span></div>
            @endforeach
        </div>

        @if ($league->type === 'h2h' && $h2hFixtures->isNotEmpty())
            <h3 class="ff-section-title">@lang('Head-to-Head Fixtures')</h3>
            <div class="ff-standings">
                @foreach ($h2hFixtures as $f)
                    <div class="ff-standing-row"><span>GW{{ $f->gameweek->number }}</span><span class="ff-standing-name">{{ $f->teamA->name ?? '-' }} {{ $f->team_a_points ?? '-' }} - {{ $f->team_b_points ?? '-' }} {{ $f->teamB->name ?? '-' }}</span></div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
