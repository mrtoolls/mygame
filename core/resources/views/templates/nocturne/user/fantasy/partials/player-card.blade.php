@php
    $player = $tp->player;
    $club = $player->club;
    $isBench = ($benchCard ?? false);
@endphp
<label class="ff-player-card {{ $isBench ? 'ff-player-card--bench' : '' }}">
    <input type="checkbox" name="starting[]" value="{{ $player->id }}" class="ff-player-starting-toggle" @checked($tp->squad_role === 'starting') hidden>
    <span class="ff-player-shirt">
        <x-fantasy-shirt :primary="$club->color_primary" :secondary="$club->color_secondary" :pattern="$club->pattern" :size="36" />
        @if ($tp->is_captain)<span class="ff-badge ff-badge--c">C</span>@endif
        @if ($tp->is_vice_captain)<span class="ff-badge ff-badge--v">V</span>@endif
    </span>
    <span class="ff-player-name">{{ $player->name }}</span>
    <span class="ff-player-meta">{{ $club->short_code }} &middot; {{ number_format($player->price, 1) }}</span>
    <span class="ff-captain-picks">
        <span class="ff-pick-c" data-player="{{ $player->id }}" title="@lang('Set as captain')">C</span>
        <span class="ff-pick-v" data-player="{{ $player->id }}" title="@lang('Set as vice-captain')">V</span>
    </span>
</label>
<input type="radio" name="captain_id" value="{{ $player->id }}" class="ff-captain-radio" @checked($tp->is_captain) hidden>
<input type="radio" name="vice_id" value="{{ $player->id }}" class="ff-vice-radio" @checked($tp->is_vice_captain) hidden>

@once
    @push('style')
    <style>
    .ff-player-card { display: grid; justify-items: center; gap: 4px; width: 76px; cursor: pointer; position: relative; padding: 6px; border-radius: 10px; transition: background .15s ease; }
    .ff-player-card:has(.ff-player-starting-toggle:not(:checked)) { opacity: .45; }
    .ff-player-shirt { position: relative; }
    .ff-badge { position: absolute; top: -4px; width: 16px; height: 16px; border-radius: 50%; font-size: 10px; font-weight: 800; display: grid; place-items: center; color: #073c2a; }
    .ff-badge--c { right: -4px; background: var(--nocturne-green, #00ef87); }
    .ff-badge--v { left: -4px; background: #ffd166; }
    .ff-player-name { font-size: 10.5px; font-weight: 700; color: #fff; text-align: center; max-width: 76px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ff-player-meta { font-size: 9.5px; color: rgba(255,255,255,.75); }
    .ff-captain-picks { display: flex; gap: 4px; margin-top: 2px; }
    .ff-pick-c, .ff-pick-v { width: 16px; height: 16px; border-radius: 50%; font-size: 9px; font-weight: 800; display: grid; place-items: center; background: rgba(255,255,255,.15); color: #fff; cursor: pointer; }
    .ff-pick-c.is-active { background: var(--nocturne-green, #00ef87); color: #073c2a; }
    .ff-pick-v.is-active { background: #ffd166; color: #073c2a; }
    .ff-player-card--bench { width: 70px; }
    </style>
    @endpush

    @push('script')
    <script>
    "use strict";
    (function() {
        var form = document.getElementById('ff-lineup-form');
        if (!form) return;
        form.addEventListener('click', function(e) {
            var card = e.target.closest('.ff-player-card');
            if (card && !e.target.closest('.ff-captain-picks')) {
                var toggle = card.querySelector('.ff-player-starting-toggle');
                toggle.checked = !toggle.checked;
                e.preventDefault();
            }
            var pickC = e.target.closest('.ff-pick-c');
            if (pickC) {
                e.preventDefault();
                var radio = form.querySelector('.ff-captain-radio[value="' + pickC.dataset.player + '"]');
                if (radio) radio.checked = true;
                refreshBadges();
            }
            var pickV = e.target.closest('.ff-pick-v');
            if (pickV) {
                e.preventDefault();
                var radioV = form.querySelector('.ff-vice-radio[value="' + pickV.dataset.player + '"]');
                if (radioV) radioV.checked = true;
                refreshBadges();
            }
        });
        function refreshBadges() {
            var captainId = (form.querySelector('.ff-captain-radio:checked') || {}).value;
            var viceId = (form.querySelector('.ff-vice-radio:checked') || {}).value;
            form.querySelectorAll('.ff-pick-c').forEach(function(el) { el.classList.toggle('is-active', el.dataset.player === captainId); });
            form.querySelectorAll('.ff-pick-v').forEach(function(el) { el.classList.toggle('is-active', el.dataset.player === viceId); });
        }
        refreshBadges();
    })();
    </script>
    @endpush
@endonce
