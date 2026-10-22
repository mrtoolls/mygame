@php
    $typeLabels = ['wager_race' => 'Wager Race', 'biggest_win' => 'Biggest Win', 'lucky_draw' => 'Lucky Draw', 'table_league' => 'Table League'];
    $metricSuffix = ['usd' => ' USD', 'points' => ' pts', 'multiplier' => 'x', 'tickets' => ' tickets'];
    $isFinal = in_array($competition->status, ['finalized', 'paid']);
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

        <section class="nocturne-comp-hero" @if($competition->banner_image) style="background-image:url('{{ getImage('assets/images/competitions/' . $competition->banner_image, '1200x600') }}')" @endif>
            <div class="nocturne-comp-hero__shade"></div>
            <div class="nocturne-comp-hero__copy">
                <span class="nocturne-comp-hero__type">{{ __($typeLabels[$competition->type] ?? $competition->type) }}</span>
                <h1>{{ __($competition->title) }}</h1>
                @if ($competition->description)<p>{{ __($competition->description) }}</p>@endif
                @if (!$isFinal)
                    <div class="nocturne-comp-timer" data-end="{{ $competition->end_at->toIso8601String() }}" data-start="{{ $competition->start_at->toIso8601String() }}">
                        <div class="nocturne-comp-timer__unit"><b data-unit="days">--</b><span>@lang('Days')</span></div>
                        <div class="nocturne-comp-timer__unit"><b data-unit="hours">--</b><span>@lang('Hrs')</span></div>
                        <div class="nocturne-comp-timer__unit"><b data-unit="minutes">--</b><span>@lang('Mins')</span></div>
                        <div class="nocturne-comp-timer__unit"><b data-unit="seconds">--</b><span>@lang('Sec')</span></div>
                    </div>
                @else
                    <span class="nocturne-comp-final-badge"><i class="ph-fill ph-check-circle"></i> @lang('Final results')</span>
                @endif
            </div>
        </section>

        <div class="nocturne-comp-layout">
            <section class="nocturne-comp-panel">
                <h2>@lang('Standings')</h2>
                <div class="nocturne-comp-table-wrap">
                    <table class="nocturne-comp-table">
                        <thead><tr><th>@lang('Rank')</th><th>@lang('Player')</th><th>@lang('Score')</th></tr></thead>
                        <tbody>
                            @forelse ($standings as $row)
                                <tr @class(['is-me' => auth()->check() && auth()->id() === $row->user_id, 'is-dq' => $row->disqualified])>
                                    <td>#{{ $row->rank }}</td>
                                    <td>{{ $row->username }} @if($row->disqualified)<span class="nocturne-comp-dq">@lang('Disqualified')</span>@endif</td>
                                    <td>{{ rtrim(rtrim(number_format((float) $row->metric_value, 4), '0'), '.') }}{{ $metricSuffix[$metricLabel] ?? '' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="nocturne-comp-empty-row">@lang('No qualifying entries yet - be the first.')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($competition->rules)
                    <h2 class="mt-4">@lang('Rules')</h2>
                    <p class="nocturne-comp-rules">{{ $competition->rules }}</p>
                @endif
            </section>

            <aside class="nocturne-comp-side">
                @if ($prizes->isNotEmpty())
                    <div class="nocturne-comp-card-box">
                        <h3>@lang('Prizes')</h3>
                        @foreach ($prizes as $prize)
                            <div class="nocturne-comp-prize-row"><span>#{{ $prize->rank }}</span><b>${{ number_format($prize->amount_usd, 2) }}</b></div>
                        @endforeach
                    </div>
                @endif

                @if ($myResult)
                    <div class="nocturne-comp-card-box">
                        <h3>@lang('Your Result')</h3>
                        <div class="nocturne-comp-prize-row"><span>@lang('Rank')</span><b>#{{ $myResult->rank }}</b></div>
                        @if ($myResult->prize_amount_usd)
                            <div class="nocturne-comp-prize-row"><span>@lang('Prize')</span><b>${{ number_format($myResult->prize_amount_usd, 2) }}</b></div>
                            <div class="nocturne-comp-prize-row"><span>@lang('Status')</span><b>{{ $myResult->isPaid() ? __('Paid') : __('Pending payout') }}</b></div>
                        @endif
                    </div>
                @endif

                @auth
                    <div class="nocturne-comp-card-box">
                        <h3>@lang('Report a Concern')</h3>
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
.nocturne-comp-hero{position:relative;border-radius:20px;overflow:hidden;background:#15131f;background-size:cover;background-position:center;min-height:260px;display:flex;align-items:flex-end;margin-top:16px}
.nocturne-comp-hero__shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(10,9,16,.2),rgba(10,9,16,.92))}
.nocturne-comp-hero__copy{position:relative;z-index:1;padding:28px}
.nocturne-comp-hero__type{display:inline-block;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#ffd782;margin-bottom:8px}
.nocturne-comp-hero__copy h1{font-size:30px;margin:0 0 8px;color:#fff}
.nocturne-comp-hero__copy p{color:var(--nocturne-text-muted,#c3c6d6);max-width:560px;margin:0 0 16px}
.nocturne-comp-timer{display:flex;gap:10px}
.nocturne-comp-timer__unit{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:8px 14px;text-align:center;min-width:64px}
.nocturne-comp-timer__unit b{display:block;font-size:22px;color:#fff}
.nocturne-comp-timer__unit span{font-size:10px;text-transform:uppercase;color:var(--nocturne-text-muted,#9aa0b4)}
.nocturne-comp-final-badge{display:inline-flex;align-items:center;gap:6px;color:#7CFFB2;font-weight:600}
.nocturne-comp-layout{display:grid;grid-template-columns:1fr 300px;gap:20px;margin-top:24px}
@media (max-width:900px){.nocturne-comp-layout{grid-template-columns:1fr}}
.nocturne-comp-panel h2{font-size:18px;margin:0 0 14px}
.nocturne-comp-table-wrap{overflow-x:auto;border-radius:14px;border:1px solid rgba(255,255,255,.08)}
.nocturne-comp-table{width:100%;border-collapse:collapse;font-size:14px}
.nocturne-comp-table th,.nocturne-comp-table td{padding:10px 14px;text-align:left;border-bottom:1px solid rgba(255,255,255,.06)}
.nocturne-comp-table th{color:var(--nocturne-text-muted,#9aa0b4);font-size:12px;text-transform:uppercase}
.nocturne-comp-table tr.is-me{background:rgba(255,215,130,.08)}
.nocturne-comp-table tr.is-dq{opacity:.5}
.nocturne-comp-dq{margin-left:8px;font-size:11px;color:#ff6b6b}
.nocturne-comp-empty-row{text-align:center;color:var(--nocturne-text-muted,#9aa0b4);padding:20px}
.nocturne-comp-rules{color:var(--nocturne-text-muted,#c3c6d6);white-space:pre-line;font-size:14px}
.nocturne-comp-card-box{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:16px;margin-bottom:16px}
.nocturne-comp-card-box h3{font-size:14px;margin:0 0 10px;text-transform:uppercase;letter-spacing:.04em}
.nocturne-comp-prize-row{display:flex;justify-content:space-between;padding:6px 0;font-size:14px;border-bottom:1px solid rgba(255,255,255,.05)}
.nocturne-comp-flag-hint{font-size:12px;color:var(--nocturne-text-muted,#9aa0b4);margin:0 0 10px}
.nocturne-comp-flag-btn{width:100%;padding:10px;border-radius:10px;border:1px solid rgba(255,255,255,.15);background:rgba(255,255,255,.06);color:#fff;cursor:pointer}
.nocturne-comp-flag-btn:hover{background:rgba(255,255,255,.12)}
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
