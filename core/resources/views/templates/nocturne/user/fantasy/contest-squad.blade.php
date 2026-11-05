@extends('Template::layouts.master')
@section('content')
<div class="nocturne-account ff-page ff-quick-builder">
    @include('Template::user.fantasy.partials.nav')
    <div class="nocturne-account-content">
        <div class="nocturne-vip-crumb"><a href="{{ route('user.fantasy.contests.index') }}">@lang('Contests')</a><i class="ph-bold ph-caret-right"></i><span>@lang('Build squad')</span></div>
        <section class="ff-page-hero"><div><span class="ff-page-kicker"><i class="ph-fill ph-strategy"></i>@lang('QUICK CONTEST SQUAD')</span><h1>{{ $contest->name }}</h1><p>@lang('Build a separate 15-player squad for this contest. Your season team will not be changed.')</p></div><div class="ff-contest-gw"><small>@lang('SHARED BUDGET')</small><strong>{{ number_format($contest->budget_tier, 1) }}</strong><small>@lang('fantasy credits')</small></div></section>
        @if ($errors->any())<div class="ff-builder-error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('user.fantasy.contests.squad.save', $contest->id) }}" id="quickSquadForm">
            @csrf
            <section class="ff-builder-panel">
                <div class="ff-builder-title"><div><span class="ff-page-kicker">@lang('YOUR LINEUP')</span><h2>@lang('Draft your squad')</h2></div><label class="ff-squad-name">@lang('Squad name')<input name="name" maxlength="60" required value="{{ old('name', $squad?->name ?? 'My quick squad') }}"></label></div>
                <div class="ff-builder-summary">
                    @foreach (['GK'=>2,'DEF'=>5,'MID'=>5,'FWD'=>3] as $position=>$needed)<div class="ff-builder-count" data-position-count="{{ $position }}"><strong data-count>0</strong><span>{{ $position }} / {{ $needed }}</span></div>@endforeach
                    <div class="ff-builder-budget"><strong><span id="budgetRemaining">{{ number_format($contest->budget_tier, 1) }}</span></strong><span>@lang('credits left')</span></div>
                </div>
                <div class="ff-builder-controls"><input type="search" id="playerSearch" placeholder="{{ __('Search players or clubs') }}" autocomplete="off"><div class="ff-position-filter"><button type="button" class="is-active" data-filter="all">@lang('All')</button>@foreach(['GK','DEF','MID','FWD'] as $position)<button type="button" data-filter="{{ $position }}">{{ $position }}</button>@endforeach</div></div>
                <div class="ff-builder-player-list" id="playerList">
                    @foreach ($players as $player)
                        @php($selected = $squad?->players->contains('player_id', $player->id))
                        <label class="ff-player-option" data-player data-available="{{ $player->is_active ? '1' : '0' }}" data-position="{{ $player->position }}" data-club="{{ $player->club_id }}" data-price="{{ $player->price }}" data-name="{{ strtolower($player->name.' '.($player->club->name ?? '')) }}">
                            <input type="checkbox" name="player_ids[]" value="{{ $player->id }}" @checked($selected || in_array($player->id, old('player_ids', [])))>
                            <span class="ff-player-pos">{{ $player->position }}</span><span class="ff-player-name">{{ $player->name }}<small>{{ $player->club->name ?? __('Club') }}@unless($player->is_active) · @lang('unavailable — replace')@endunless</small></span>
                            <span class="ff-player-rating">{{ number_format($player->rating, 1) }}<small>@lang('rating')</small></span><span class="ff-player-price">{{ number_format($player->price, 1) }}<small>@lang('credits')</small></span>
                        </label>
                    @endforeach
                </div>
                <div class="ff-builder-footer"><span>@lang('15 players · 2 GK, 5 DEF, 5 MID, 3 FWD · maximum 3 from one club')</span><button class="btn btn--gradient" id="saveSquadButton" type="submit">@lang('Save contest squad')</button></div>
            </section>
        </form>
    </div>
</div>
@endsection

@push('style')
<style>
.ff-quick-builder{--qb-bg:#11162b;--qb-panel:#161d36;--qb-line:#293251;--qb-muted:#929fc4;--qb-green:#00ef87}.ff-builder-panel{padding:20px;border:1px solid var(--qb-line);border-radius:20px;background:linear-gradient(145deg,#151b33,#101529)}.ff-builder-title{display:flex;justify-content:space-between;align-items:end;gap:16px;margin-bottom:16px}.ff-builder-title h2{margin:0;color:#f3f5ff;font-size:20px;font-weight:700}.ff-squad-name{display:grid;gap:5px;color:var(--qb-muted);font-size:11px}.ff-squad-name input,.ff-builder-controls input{min-height:42px;padding:0 12px;border:1px solid var(--qb-line);border-radius:11px;background:#0c1123;color:#f4f6ff}.ff-squad-name input{width:min(270px,50vw)}.ff-builder-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) minmax(120px,1.2fr);gap:8px;margin-bottom:14px}.ff-builder-count,.ff-builder-budget{display:grid;justify-items:center;gap:2px;padding:10px 6px;border:1px solid var(--qb-line);border-radius:12px;background:#10162b;color:var(--qb-muted)}.ff-builder-count strong,.ff-builder-budget strong{color:#f5f6ff;font-size:18px}.ff-builder-count span,.ff-builder-budget span{font-size:9px}.ff-builder-count.is-complete{border-color:rgba(0,239,135,.45)}.ff-builder-count.is-complete strong,.ff-builder-budget strong{color:var(--qb-green)}.ff-builder-budget{background:rgba(0,239,135,.07)}.ff-builder-controls{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:10px}.ff-builder-controls input{width:min(300px,100%)}.ff-position-filter{display:flex;gap:5px;flex-wrap:wrap}.ff-position-filter button{min-height:34px;padding:0 10px;border:1px solid var(--qb-line);border-radius:9px;background:#11172c;color:var(--qb-muted);font-size:10px}.ff-position-filter button.is-active{border-color:var(--qb-green);color:var(--qb-green)}.ff-builder-player-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;max-height:580px;overflow:auto;padding:2px}.ff-player-option{display:flex;align-items:center;gap:9px;min-width:0;padding:10px;border:1px solid var(--qb-line);border-radius:12px;background:rgba(10,15,30,.55);cursor:pointer}.ff-player-option:has(input:checked){border-color:rgba(0,239,135,.6);background:rgba(0,239,135,.07)}.ff-player-option input{accent-color:var(--qb-green);width:16px;height:16px;flex:0 0 auto}.ff-player-option[hidden]{display:none}.ff-player-pos{display:grid;place-items:center;min-width:34px;height:30px;border-radius:8px;background:#242d4d;color:var(--qb-green);font-size:9px;font-weight:700}.ff-player-name{flex:1;min-width:0;overflow:hidden;color:#f2f4ff;font-size:12px;font-weight:650;text-overflow:ellipsis;white-space:nowrap}.ff-player-name small,.ff-player-rating small,.ff-player-price small{display:block;color:var(--qb-muted);font-size:9px;font-weight:400}.ff-player-rating,.ff-player-price{flex:0 0 auto;color:#eff2ff;font-size:11px;text-align:right}.ff-player-price{color:var(--qb-green)}.ff-builder-footer{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-top:14px;color:var(--qb-muted);font-size:10px}.ff-builder-footer .btn{min-height:44px;padding:0 18px;border-radius:11px;white-space:nowrap}.ff-builder-error{margin-bottom:12px;padding:11px 14px;border:1px solid rgba(255,90,112,.35);border-radius:12px;background:rgba(255,90,112,.08);color:#ff8b9b;font-size:12px}
@media(max-width:760px){.ff-builder-panel{padding:14px;border-radius:16px}.ff-builder-title{align-items:stretch;flex-direction:column}.ff-squad-name input{width:100%}.ff-builder-summary{grid-template-columns:repeat(4,minmax(0,1fr))}.ff-builder-budget{grid-column:1/-1;display:flex;justify-content:center;align-items:center;gap:7px;padding:8px}.ff-builder-controls{align-items:stretch;flex-direction:column}.ff-builder-controls input{width:100%}.ff-builder-player-list{grid-template-columns:1fr;max-height:55vh}.ff-builder-footer{align-items:stretch;flex-direction:column}.ff-builder-footer .btn{width:100%;white-space:normal}.ff-quick-builder .ff-page-hero{align-items:flex-start}.ff-quick-builder .ff-page-hero p{max-width:100%}}
</style>
@endpush

@push('script')
<script>
(() => {
    const budgetLimit = Number(@json((float) $contest->budget_tier));
    const list = document.getElementById('playerList');
    const boxes = [...list.querySelectorAll('[data-player]')];
    const labels = {GK:2,DEF:5,MID:5,FWD:3};
    const update = () => {
        const picked = boxes.filter(row => row.querySelector('input').checked);
        const spent = picked.reduce((sum,row) => sum + Number(row.dataset.price),0);
        const byPos = Object.fromEntries(Object.keys(labels).map(pos => [pos,picked.filter(row => row.dataset.position === pos).length]));
        document.getElementById('budgetRemaining').textContent = Math.max(0,budgetLimit-spent).toFixed(1);
        Object.entries(labels).forEach(([pos,limit]) => {
            const badge = document.querySelector(`[data-position-count="${pos}"]`);
            badge.querySelector('[data-count]').textContent = `${byPos[pos]}/${limit}`;
            badge.classList.toggle('is-complete',byPos[pos] === limit);
        });
        boxes.forEach(row => {
            const selected = row.querySelector('input').checked;
            const clubCount = picked.filter(item => item.dataset.club === row.dataset.club).length;
            row.querySelector('input').disabled = !selected && (row.dataset.available !== '1' || picked.length >= 15 || byPos[row.dataset.position] >= labels[row.dataset.position] || clubCount >= 3 || spent + Number(row.dataset.price) > budgetLimit);
        });
        document.getElementById('saveSquadButton').disabled = picked.length !== 15 || spent > budgetLimit || Object.entries(labels).some(([pos,n]) => byPos[pos] !== n);
    };
    boxes.forEach(row => row.querySelector('input').addEventListener('change',update));
    const search = document.getElementById('playerSearch');
    let active = 'all';
    const filter = () => boxes.forEach(row => { row.hidden = (active !== 'all' && row.dataset.position !== active) || !row.dataset.name.includes(search.value.trim().toLowerCase()); });
    search.addEventListener('input',filter);
    document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click',() => { active=button.dataset.filter;document.querySelectorAll('[data-filter]').forEach(item=>item.classList.toggle('is-active',item===button));filter(); }));
    update();
})();
</script>
@endpush
