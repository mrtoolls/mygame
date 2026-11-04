@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page" id="ff-match-root"
    data-snapshot="{{ route('user.fantasy.fixtures.snapshot', $fixture->id) }}"
    data-verify="{{ route('user.fantasy.fixtures.verify', $fixture->id) }}"
    data-my-players="{{ implode(',', $myTeamPlayerIds) }}">
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('home') }}">@lang('Home')</a><i class="ph-bold ph-caret-right"></i><a href="{{ route('user.fantasy.fixtures.index') }}">@lang('Fixtures')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Live Match')</span></div>

        <div class="ff-scoreboard">
            <div class="ff-scoreboard__club"><x-fantasy-shirt :primary="$fixture->homeClub->color_primary" :secondary="$fixture->homeClub->color_secondary" :pattern="$fixture->homeClub->pattern" :size="56" /><span>{{ $fixture->homeClub->name }}</span></div>
            <div class="ff-scoreboard__mid">
                <div class="ff-scoreboard__score" id="ff-score">{{ $fixture->home_score ?? 0 }} - {{ $fixture->away_score ?? 0 }}</div>
                <div class="ff-scoreboard__status" id="ff-status">{{ ucfirst($fixture->status) }}</div>
                <div class="ff-scoreboard__minute" id="ff-minute"></div>
            </div>
            <div class="ff-scoreboard__club"><x-fantasy-shirt :primary="$fixture->awayClub->color_primary" :secondary="$fixture->awayClub->color_secondary" :pattern="$fixture->awayClub->pattern" :size="56" /><span>{{ $fixture->awayClub->name }}</span></div>
        </div>

        <div class="ff-match-verify" id="ff-verify-box" hidden>
            <button type="button" id="ff-verify-btn" class="btn btn--sm">@lang('Verify this match')</button>
            <span id="ff-verify-result"></span>
        </div>

        <h3 class="ff-section-title">@lang('Match Events')</h3>
        <div class="ff-timeline" id="ff-timeline"></div>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-scoreboard { display: flex; align-items: center; justify-content: space-between; background: #0d1023; border: 1px solid #2c3453; border-radius: 16px; padding: 20px; margin-bottom: 16px; }
.ff-scoreboard__club { display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; color: #fff; text-align: center; max-width: 110px; }
.ff-scoreboard__mid { text-align: center; }
.ff-scoreboard__score { font-size: 28px; font-weight: 900; color: var(--nocturne-green, #00ef87); }
.ff-scoreboard__status { font-size: 11px; color: var(--nocturne-muted, #929abc); text-transform: uppercase; letter-spacing: .05em; }
.ff-scoreboard__minute { font-size: 13px; font-weight: 800; color: #ff9a4d; margin-top: 2px; }
.ff-match-verify { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; font-size: 12px; color: var(--nocturne-muted, #929abc); }
.ff-match-verify .btn--sm { padding: 6px 12px; border-radius: 8px; background: #2c3453; color: #fff; border: 0; font-size: 12px; }
.ff-timeline { display: flex; flex-direction: column; gap: 8px; }
.ff-event-row { display: flex; align-items: center; gap: 10px; background: #0d1023; border: 1px solid #2c3453; border-radius: 10px; padding: 10px 14px; font-size: 13px; color: #fff; }
.ff-event-row.is-mine { border-color: var(--nocturne-green, #00ef87); background: rgba(0,239,135,.06); }
.ff-event-minute { font-weight: 800; color: var(--nocturne-muted, #929abc); min-width: 32px; }
.ff-event-icon { font-size: 16px; }
</style>
@endpush

@push('script')
<script>
"use strict";
(function() {
    var root = document.getElementById('ff-match-root');
    var myPlayers = (root.dataset.myPlayers || '').split(',').filter(Boolean);
    var since = -1;
    var pollTimer = null;
    var icons = { goal: 'âš½', penalty_scored: 'âš½ P', penalty_missed: 'âŒ P', own_goal: 'âš½ OG', yellow: 'ðŸŸ¨', red: 'ðŸŸ¥', substitution: 'ðŸ”„', injury: 'ðŸš‘' };
    var labels = { goal: 'Goal', penalty_scored: 'Penalty scored', penalty_missed: 'Penalty missed', own_goal: 'Own goal', yellow: 'Yellow card', red: 'Red card', substitution: 'Substitution', injury: 'Injury' };

    function render(data) {
        document.getElementById('ff-score').textContent = data.home_score + ' - ' + data.away_score;
        document.getElementById('ff-status').textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
        document.getElementById('ff-minute').textContent = data.status === 'live' ? data.minute + "'" : (data.status === 'final' ? 'Full Time' : '');

        var timeline = document.getElementById('ff-timeline');
        timeline.innerHTML = '';
        if (!data.events.length) {
            timeline.innerHTML = '<p class="text-muted text-center">No events yet.</p>';
        }
        data.events.slice().reverse().forEach(function(e) {
            var mine = myPlayers.indexOf(String(e.player_id)) !== -1 || myPlayers.indexOf(String(e.secondary_player_id)) !== -1;
            var row = document.createElement('div');
            row.className = 'ff-event-row' + (mine ? ' is-mine' : '');
            row.innerHTML = '<span class="ff-event-minute">' + e.minute + "'</span><span class=\"ff-event-icon\">" + (icons[e.type] || 'â€¢') + '</span><span>' + (labels[e.type] || e.type) + '</span>';
            timeline.appendChild(row);
        });

        if (data.status === 'final') {
            document.getElementById('ff-verify-box').hidden = false;
        }
        since = data.count;
    }

    function poll() {
        if (document.hidden) { schedule(7000); return; }
        fetch(root.dataset.snapshot + '?since=' + since, { headers: { 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.unchanged) render(data);
                if (data.status !== 'final') schedule(4000); else schedule(null);
            })
            .catch(function() { schedule(5000); });
    }
    function schedule(ms) { if (pollTimer) clearTimeout(pollTimer); if (ms) pollTimer = setTimeout(poll, ms); }
    document.addEventListener('visibilitychange', function() { if (!document.hidden) poll(); });
    poll();

    var verifyBtn = document.getElementById('ff-verify-btn');
    verifyBtn.addEventListener('click', function() {
        verifyBtn.disabled = true;
        fetch(root.dataset.verify, { headers: { 'Accept': 'application/json' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('ff-verify-result').textContent = data.message;
                document.getElementById('ff-verify-result').style.color = data.verified ? '#00ef87' : '#ff5c7a';
            })
            .finally(function() { verifyBtn.disabled = false; });
    });
})();
</script>
@endpush
