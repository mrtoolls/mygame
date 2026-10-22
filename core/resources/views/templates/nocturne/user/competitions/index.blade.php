@php
    $typeLabels = ['wager_race' => 'Wager Race', 'biggest_win' => 'Biggest Win', 'lucky_draw' => 'Lucky Draw', 'table_league' => 'Table League'];
    $typeIcons = ['wager_race' => 'ph-fill ph-flame', 'biggest_win' => 'ph-fill ph-trophy', 'lucky_draw' => 'ph-fill ph-ticket', 'table_league' => 'ph-fill ph-chess-king'];
@endphp
@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account nocturne-competitions-page">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Competitions')</span></div>

        <section class="nocturne-comp-header">
            <h1><i class="ph-fill ph-trophy"></i> @lang('Competitions')</h1>
            <p>@lang('Compete for real prizes across Wager Races, Biggest Win, Lucky Draws and the Table League.')</p>
        </section>

        @foreach ([['live', $live, 'Live Now'], ['upcoming', $upcoming, 'Upcoming'], ['past', $past, 'Past Competitions']] as [$key, $list, $heading])
            <section class="nocturne-comp-section">
                <h2>{{ __($heading) }}</h2>
                @if ($list->isEmpty())
                    <p class="nocturne-comp-empty">@lang('Nothing here right now - check back soon.')</p>
                @else
                    <div class="nocturne-comp-grid">
                        @foreach ($list as $competition)
                            @php $prizePool = $competition->prizes->sum('amount_usd'); @endphp
                            <a href="{{ route('user.competitions.show', $competition->slug) }}" class="nocturne-comp-card nocturne-comp-card--{{ $key }}">
                                @if ($competition->banner_image)
                                    <div class="nocturne-comp-card__banner" style="background-image:url('{{ getImage('assets/images/competitions/' . $competition->banner_image, '600x300') }}')"></div>
                                @endif
                                <div class="nocturne-comp-card__body">
                                    <span class="nocturne-comp-card__type"><i class="{{ $typeIcons[$competition->type] ?? 'ph-fill ph-trophy' }}"></i>{{ __($typeLabels[$competition->type] ?? $competition->type) }}</span>
                                    <h3>{{ __($competition->title) }}</h3>
                                    @if ($prizePool > 0)
                                        <div class="nocturne-comp-card__pool">${{ number_format($prizePool, 0) }} <small>@lang('prize pool')</small></div>
                                    @endif
                                    <div class="nocturne-comp-card__window">
                                        @if ($key === 'upcoming')
                                            <i class="ph-bold ph-calendar"></i> @lang('Starts') {{ showDateTime($competition->start_at) }}
                                        @else
                                            <i class="ph-bold ph-clock-countdown"></i> @lang('Ends') {{ showDateTime($competition->end_at) }}
                                        @endif
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</div>

<style>
.nocturne-comp-header{padding:28px 0 8px}
.nocturne-comp-header h1{display:flex;align-items:center;gap:10px;font-size:28px;margin:0 0 6px}
.nocturne-comp-header p{color:var(--nocturne-text-muted,#9aa0b4);margin:0}
.nocturne-comp-section{margin-top:28px}
.nocturne-comp-section h2{font-size:18px;margin:0 0 14px;letter-spacing:.02em}
.nocturne-comp-empty{color:var(--nocturne-text-muted,#9aa0b4);font-size:14px}
.nocturne-comp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px}
.nocturne-comp-card{display:block;border-radius:16px;overflow:hidden;background:linear-gradient(160deg,rgba(255,255,255,.06),rgba(255,255,255,.02));border:1px solid rgba(255,255,255,.08);text-decoration:none;transition:transform .15s ease,border-color .15s ease}
.nocturne-comp-card:hover{transform:translateY(-3px);border-color:rgba(255,215,130,.35)}
.nocturne-comp-card__banner{height:120px;background-size:cover;background-position:center}
.nocturne-comp-card__body{padding:16px}
.nocturne-comp-card__type{display:inline-flex;align-items:center;gap:6px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#ffd782;margin-bottom:8px}
.nocturne-comp-card__body h3{font-size:17px;margin:0 0 8px;color:#fff}
.nocturne-comp-card__pool{font-size:20px;font-weight:700;color:#7CFFB2;margin-bottom:8px}
.nocturne-comp-card__pool small{font-size:11px;font-weight:400;color:var(--nocturne-text-muted,#9aa0b4);text-transform:none}
.nocturne-comp-card__window{font-size:13px;color:var(--nocturne-text-muted,#9aa0b4);display:flex;align-items:center;gap:6px}
.nocturne-comp-card--live{border-color:rgba(124,255,178,.3)}
</style>
@endsection
