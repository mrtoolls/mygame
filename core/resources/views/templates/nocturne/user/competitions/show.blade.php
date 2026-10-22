@php
    $typeLabels = ['wager_race' => 'Wager Race', 'biggest_win' => 'Biggest Win', 'lucky_draw' => 'Lucky Draw', 'table_league' => 'Table League'];
    $typeIcons = ['wager_race' => 'ph-fill ph-flame', 'biggest_win' => 'ph-fill ph-trophy', 'lucky_draw' => 'ph-fill ph-ticket', 'table_league' => 'ph-fill ph-chess-king'];
    $typeColors = ['wager_race' => '#ff9a4d', 'biggest_win' => '#ffd166', 'lucky_draw' => '#c58bff', 'table_league' => '#00ef87'];
    $accent = $typeColors[$competition->type] ?? '#00ef87';
    $metricSuffix = ['usd' => ' USD', 'points' => ' pts', 'multiplier' => 'x', 'tickets' => ' tickets'];
    $isFinal = in_array($competition->status, ['finalized', 'paid']);
    $medals = ['🥇', '🥈', '🥉'];
@endphp
@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account nocturne-competition-page">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb">
            <a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i>
            <a href="{{ route('user.competitions.index') }}">@lang('Competitions')</a><i class="ph-bold ph-caret-right"></i>
            <span>{{ __($competition->title) }}</span>
        </div>

        <section class="nocturne-comp-hero" @if($competition->banner_image) data-has-image @endif style="--comp-accent:{{ $accent }}@if($competition->banner_image);background-image:url('{{ getImage('assets/images/competitions/' . $competition->banner_image, '1200x600') }}')@endif">
            <div class="nocturne-comp-hero__shade"></div>
            <div class="nocturne-comp-hero__glow"></div>
            <div class="nocturne-comp-hero__copy">
                <span class="nocturne-comp-hero__type"><i class="{{ $typeIcons[$competition->type] ?? 'ph-fill ph-trophy' }}"></i>{{ __($typeLabels[$competition->type] ?? $competition->type) }}</span>
                <h1>{{ __($competition->title) }}</h1>
                @if ($competition->description)<p>{{ __($competition->description) }}</p>@endif
                @if (!$isFinal)
                    <div class="nocturne-comp-timer" data-end="{{ $competition->end_at->toIso8601String() }}" data-start="{{ $competition->start_at->toIso8601String() }}">
                        <span class="nocturne-comp-timer__label"><i class="ph-bold ph-hourglass"></i>{{ $competition->status === 'scheduled' ? __('Starts in') : __('Ends in') }}</span>
                        <div class="nocturne-comp-timer__units">
                            <div class="nocturne-comp-timer__unit"><b data-unit="days">--</b><span>@lang('Days')</span></div>
                            <div class="nocturne-comp-timer__unit"><b data-unit="hours">--</b><span>@lang('Hrs')</span></div>
                            <div class="nocturne-comp-timer__unit"><b data-unit="minutes">--</b><span>@lang('Mins')</span></div>
                            <div class="nocturne-comp-timer__unit"><b data-unit="seconds">--</b><span>@lang('Sec')</span></div>
                        </div>
                    </div>
                @else
                    <span class="nocturne-comp-final-badge"><i class="ph-fill ph-check-circle"></i> @lang('Final results')</span>
                @endif
            </div>
        </section>

        <div class="nocturne-comp-layout">
            <section class="nocturne-comp-panel">
                <h2><i class="ph-fill ph-ranking"></i>@lang('Standings')</h2>
                <div class="nocturne-comp-table-wrap">
                    <table class="nocturne-comp-table">
                        <thead><tr><th class="is-rank">@lang('Rank')</th><th>@lang('Player')</th><th class="is-score">@lang('Score')</th></tr></thead>
                        <tbody>
                            @forelse ($standings as $row)
                                <tr @class(['is-me' => auth()->check() && auth()->id() === $row->user_id, 'is-dq' => $row->disqualified, 'is-top' => $row->rank <= 3])>
                                    <td class="is-rank">@if($row->rank <= 3)<span class="nocturne-comp-medal">{{ $medals[$row->rank - 1] }}</span>@else<span class="nocturne-comp-rank-num">#{{ $row->rank }}</span>@endif</td>
                                    <td>
                                        <span class="nocturne-comp-player"><span class="nocturne-comp-player__avatar">{{ strtoupper(substr($row->username, 0, 1)) }}</span>{{ $row->username }}</span>
                                        @if($row->disqualified)<span class="nocturne-comp-dq">@lang('Disqualified')</span>@endif
                                    </td>
                                    <td class="is-score">{{ rtrim(rtrim(number_format((float) $row->metric_value, 4), '0'), '.') }}{{ $metricSuffix[$metricLabel] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="nocturne-comp-empty-row"><i class="ph-fill ph-confetti"></i>@lang('No qualifying entries yet - be the first.')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($competition->rules)
                    <h2 class="mt-4"><i class="ph-fill ph-scroll"></i>@lang('Rules')</h2>
                    <p class="nocturne-comp-rules">{{ $competition->rules }}</p>
                @endif
            </section>

            <aside class="nocturne-comp-side">
                @if ($prizes->isNotEmpty())
                    <div class="nocturne-comp-card-box">
                        <h3><i class="ph-fill ph-trophy"></i>@lang('Prizes')</h3>
                        @foreach ($prizes as $prize)
                            <div class="nocturne-comp-prize-row"><span>{{ $prize->rank <= 3 ? $medals[$prize->rank - 1] : '#' . $prize->rank }}</span><b>${{ number_format($prize->amount_usd, 2) }}</b></div>
                        @endforeach
                    </div>
                @endif

                @if ($myResult)
                    <div class="nocturne-comp-card-box nocturne-comp-card-box--accent">
                        <h3><i class="ph-fill ph-user-circle"></i>@lang('Your Result')</h3>
                        <div class="nocturne-comp-prize-row"><span>@lang('Rank')</span><b>#{{ $myResult->rank }}</b></div>
                        @if ($myResult->prize_amount_usd)
                            <div class="nocturne-comp-prize-row"><span>@lang('Prize')</span><b>${{ number_format($myResult->prize_amount_usd, 2) }}</b></div>
                            <div class="nocturne-comp-prize-row"><span>@lang('Status')</span><b>{{ $myResult->isPaid() ? __('Paid') : __('Pending payout') }}</b></div>
                        @endif
                    </div>
                @endif

                @auth
                    <div class="nocturne-comp-card-box">
                        <h3><i class="ph-fill ph-flag"></i>@lang('Report a Concern')</h3>
                        <p class="nocturne-comp-flag-hint">@lang('Suspect collusion or cheating? Let us know before prizes are paid.')</p>
                        <form action="{{ route('user.competitions.flag', $competition->slug) }}" method="POST">
                            @csrf
                            <input type="text" name="related_username" class="form-control mb-2" placeholder="@lang('Username (optional)')">
                            <textarea name="reason" class="form-control mb-2" rows="3" placeholder="@lang('What happened?')" required></textarea>
                            <button type="submit" class="nocturne-comp-flag-btn">@lang('Submit Report')</button>
                        </form>
                    </div>
                @endauth
            </aside>
        </div>
    </div>
</div>

<style>
.nocturne-competition-page{grid-template-columns:minmax(0,1fr)}
.nocturne-comp-hero{position:relative;overflow:hidden;border-radius:22px;background:radial-gradient(ellipse at 85% 15%,color-mix(in srgb,var(--comp-accent) 20%,transparent),transparent 45%),linear-gradient(125deg,#171d37,#0e1226);background-size:cover;background-position:center;min-height:240px;display:flex;align-items:flex-end;margin-top:16px;border:1px solid rgba(255,255,255,.08)}
.nocturne-comp-hero[data-has-image]{background-blend-mode:normal}
.nocturne-comp-hero__shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,9,16,.2),rgba(10,9,16,.92))}
.nocturne-comp-hero__glow{position:absolute;top:-50%;right:-10%;width:360px;height:360px;background:radial-gradient(circle,color-mix(in srgb,var(--comp-accent) 25%,transparent),transparent 70%);pointer-events:none}
.nocturne-comp-hero__copy{position:relative;z-index:1;padding:clamp(22px,4vw,34px)}
.nocturne-comp-hero__type{display:inline-flex;align-items:center;gap:7px;font-size:11px;text-transform:uppercase;font-weight:900;letter-spacing:.08em;color:var(--comp-accent);margin-bottom:10px}
.nocturne-comp-hero__copy h1{font-size:clamp(24px,4vw,32px);margin:0 0 8px;color:#fff;font-weight:950}
.nocturne-comp-hero__copy p{color:var(--nocturne-text-muted,#c3c6d6);max-width:560px;margin:0 0 18px;font-size:14px;line-height:1.6}
.nocturne-comp-timer__label{display:flex;align-items:center;gap:6px;margin-bottom:8px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--nocturne-text-muted,#9aa0b4)}
.nocturne-comp-timer__units{display:flex;gap:10px}
.nocturne-comp-timer__unit{background:rgba(255,255,255,.07);border:1px solid color-mix(in srgb,var(--comp-accent) 30%,rgba(255,255,255,.1));border-radius:13px;padding:9px 15px;text-align:center;min-width:64px}
.nocturne-comp-timer__unit b{display:block;font-size:22px;font-weight:950;color:#fff;font-variant-numeric:tabular-nums}
.nocturne-comp-timer__unit span{font-size:9.5px;text-transform:uppercase;color:var(--nocturne-text-muted,#9aa0b4);font-weight:700}
.nocturne-comp-final-badge{display:inline-flex;align-items:center;gap:6px;color:#7CFFB2;font-weight:800;font-size:14px}

.nocturne-comp-layout{display:grid;grid-template-columns:1fr 300px;gap:20px;margin-top:24px}
@media (max-width:900px){.nocturne-comp-layout{grid-template-columns:1fr}}
.nocturne-comp-panel h2{display:flex;align-items:center;gap:8px;font-size:17px;margin:0 0 14px;font-weight:900;color:#fff}
.nocturne-comp-panel h2 i{color:var(--comp-accent)}
.nocturne-comp-table-wrap{overflow-x:auto;border-radius:16px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.015)}
.nocturne-comp-table{width:100%;min-width:360px;border-collapse:collapse;font-size:13.5px}
.nocturne-comp-table th,.nocturne-comp-table td{padding:12px 16px;text-align:left;border-bottom:1px solid rgba(255,255,255,.06);white-space:nowrap}
.nocturne-comp-table th{color:var(--nocturne-text-muted,#9aa0b4);font-size:11px;text-transform:uppercase;letter-spacing:.04em;font-weight:800}
.nocturne-comp-table th.is-rank,.nocturne-comp-table td.is-rank{width:64px}
.nocturne-comp-table th.is-score,.nocturne-comp-table td.is-score{text-align:right;font-weight:800;color:#fff}
.nocturne-comp-table tbody tr:nth-child(even){background:rgba(255,255,255,.015)}
.nocturne-comp-table tr.is-top td.is-score{color:var(--comp-accent)}
.nocturne-comp-table tr.is-me{background:color-mix(in srgb,var(--comp-accent) 10%,transparent) !important;box-shadow:inset 2px 0 0 var(--comp-accent)}
.nocturne-comp-table tr.is-dq{opacity:.5}
.nocturne-comp-medal{font-size:18px}
.nocturne-comp-rank-num{color:var(--nocturne-text-muted,#9aa0b4);font-weight:700}
.nocturne-comp-player{display:inline-flex;align-items:center;gap:9px;color:#fff;font-weight:800}
.nocturne-comp-player__avatar{display:grid;place-items:center;width:26px;height:26px;border-radius:50%;background:color-mix(in srgb,var(--comp-accent) 25%,#232a47);color:var(--comp-accent);font-size:11px;font-weight:950;flex:0 0 auto}
.nocturne-comp-dq{margin-left:8px;font-size:10.5px;color:#ff6b6b;font-weight:700}
.nocturne-comp-empty-row{text-align:center;color:var(--nocturne-text-muted,#9aa0b4);padding:28px;white-space:normal}
.nocturne-comp-empty-row i{display:block;font-size:24px;color:#ffd782;margin-bottom:8px}
.nocturne-comp-rules{color:var(--nocturne-text-muted,#c3c6d6);white-space:pre-line;font-size:13.5px;line-height:1.7}
.nocturne-comp-card-box{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:18px;margin-bottom:16px}
.nocturne-comp-card-box--accent{border-color:color-mix(in srgb,var(--comp-accent) 35%,transparent);background:color-mix(in srgb,var(--comp-accent) 7%,transparent)}
.nocturne-comp-card-box h3{display:flex;align-items:center;gap:7px;font-size:13px;margin:0 0 12px;text-transform:uppercase;letter-spacing:.04em;font-weight:900;color:#fff}
.nocturne-comp-card-box h3 i{color:var(--comp-accent)}
.nocturne-comp-prize-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;font-size:14px;border-bottom:1px solid rgba(255,255,255,.05)}
.nocturne-comp-prize-row:last-child{border-bottom:0}
.nocturne-comp-prize-row b{color:#fff;font-weight:900}
.nocturne-comp-flag-hint{font-size:12px;color:var(--nocturne-text-muted,#9aa0b4);margin:0 0 10px;line-height:1.5}
.nocturne-comp-flag-btn{width:100%;padding:11px;border-radius:11px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06);color:#fff;font-weight:800;cursor:pointer;transition:background .15s ease}
.nocturne-comp-flag-btn:hover{background:rgba(255,255,255,.12)}
@media(max-width:575px){.nocturne-comp-timer__units{gap:6px}.nocturne-comp-timer__unit{min-width:0;flex:1;padding:7px 4px}.nocturne-comp-timer__unit b{font-size:17px}}
</style>

<script>
(function () {
    var timerEl = document.querySelector('.nocturne-comp-timer');
    if (!timerEl) return;
    var end = new Date(timerEl.dataset.end).getTime();

    function tick() {
        var diff = Math.max(0, end - Date.now());
        var days = Math.floor(diff / 86400000);
        var hours = Math.floor((diff % 86400000) / 3600000);
        var minutes = Math.floor((diff % 3600000) / 60000);
        var seconds = Math.floor((diff % 60000) / 1000);
        timerEl.querySelector('[data-unit="days"]').textContent = String(days);
        timerEl.querySelector('[data-unit="hours"]').textContent = String(hours).padStart(2, '0');
        timerEl.querySelector('[data-unit="minutes"]').textContent = String(minutes).padStart(2, '0');
        timerEl.querySelector('[data-unit="seconds"]').textContent = String(seconds).padStart(2, '0');
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
@endsection
