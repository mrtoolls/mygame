@php
    $locked = $competition->exists && $competition->isScoringLocked();
    $pointsConfig = data_get($competition->type_settings, 'points', ['win' => 3, 'draw' => 1, 'loss' => 0]);
@endphp

<div class="row">
    <div class="col-md-8">
        <div class="form-group">
            <label>@lang('Title')</label>
            <input type="text" class="form-control" name="title" value="{{ old('title', $competition->title) }}" required>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-group">
            <label>@lang('Status')</label>
            <select name="status" class="form-control">
                @foreach (['draft', 'scheduled', 'live', 'ended', 'finalized', 'paid'] as $status)
                    <option value="{{ $status }}" {{ old('status', $competition->status ?: 'draft') === $status ? 'selected' : '' }}>{{ __(ucfirst($status)) }}</option>
                @endforeach
            </select>
            <small class="text-muted">@lang('Set to Scheduled when ready to go live - this locks scoring settings below.')</small>
        </div>
    </div>

    <div class="col-md-12">
        <div class="form-group">
            <label>@lang('Description')</label>
            <textarea class="form-control nicEdit" name="description" rows="5" maxlength="6000">{{ old('description', $competition->description) }}</textarea>
            <small class="text-muted">@lang('Use the formatting toolbar for emphasis, headings, lists and links. Unsafe markup is removed automatically.')</small>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            <label>@lang('Rules (shown to players)')</label>
            <textarea class="form-control nicEdit" name="rules" rows="8" maxlength="16000">{{ old('rules', $competition->rules) }}</textarea>
            <small class="text-muted">@lang('Formatting is preserved on the competition page. Scripts, embeds and unsafe markup are not allowed.')</small>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-group">
            <label>@lang('Banner Image (optional)')</label>
            @if ($competition->banner_image)
                <div class="mb-2"><img src="{{ getImage('assets/images/competitions/' . $competition->banner_image, '1200x600') }}" alt="" style="max-width:260px;border-radius:8px"></div>
            @endif
            <input type="file" class="form-control" name="banner_image" accept=".png,.jpg,.jpeg,.webp">
            <small class="text-muted">@lang('Recommended 1200x600')</small>
        </div>
    </div>

    <div class="col-md-12"><hr><h6 class="mb-3">@lang('Scoring Settings')@if($locked) <span class="badge badge--warning">@lang('Locked - competition is past draft/scheduled')</span>@endif</h6></div>

    <fieldset {{ $locked ? 'disabled' : '' }} style="width:100%">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Type')</label>
                <select name="type" class="form-control" {{ $competition->exists ? 'disabled' : '' }}>
                    @foreach (['wager_race' => 'Wager Race', 'biggest_win' => 'Biggest Win', 'lucky_draw' => 'Lucky Draw', 'table_league' => 'Table League'] as $value => $label)
                        <option value="{{ $value }}" {{ old('type', $competition->type) === $value ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
                @if ($competition->exists)
                    <input type="hidden" name="type" value="{{ $competition->type }}">
                    <small class="text-muted">@lang('Type cannot change after creation.')</small>
                @endif
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Starts At')</label>
                <input type="datetime-local" class="form-control" name="start_at" value="{{ old('start_at', $competition->start_at?->format('Y-m-d\TH:i')) }}" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Ends At')</label>
                <input type="datetime-local" class="form-control" name="end_at" value="{{ old('end_at', $competition->end_at?->format('Y-m-d\TH:i')) }}" required>
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Minimum Stake (USD)')</label>
                <input type="number" step="0.01" min="0" class="form-control" name="min_bet_usd" value="{{ old('min_bet_usd', $competition->min_bet_usd) }}">
                <small class="text-muted">@lang('Games below this stake, converted via the locked table rate, are excluded.')</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Eligible Games')</label>
                <input type="text" class="form-control" name="eligible_games" value="{{ old('eligible_games', $competition->eligible_games ? implode(', ', $competition->eligible_games) : '') }}" placeholder="@lang('e.g. ludo, chess, draughts - blank = all')">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Minimum Account Age (days)')</label>
                <input type="number" min="0" class="form-control" name="min_account_age_days" value="{{ old('min_account_age_days', $competition->min_account_age_days) }}">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Minimum Games (for prize eligibility)')</label>
                <input type="number" min="0" class="form-control" name="min_games" value="{{ old('min_games', $competition->min_games) }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Minimum Distinct Opponents')</label>
                <input type="number" min="0" class="form-control" name="min_distinct_opponents" value="{{ old('min_distinct_opponents', $competition->min_distinct_opponents) }}">
                <small class="text-muted">@lang('table_league only - counted from fully-counted games.')</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Max Games Per Opponent Pair')</label>
                <input type="number" min="0" class="form-control" name="max_games_per_opponent_pair" value="{{ old('max_games_per_opponent_pair', $competition->max_games_per_opponent_pair) }}">
                <small class="text-muted">@lang('Anti-collusion cap. Blank = unlimited.')</small>
            </div>
        </div>

        <div class="col-md-12"><p class="text-muted mb-2">@lang('Lucky Draw settings')</p></div>
        <div class="col-md-6">
            <div class="form-group">
                <label>@lang('USD Per Ticket')</label>
                <input type="number" step="0.01" min="0" class="form-control" name="ticket_usd_per_ticket" value="{{ old('ticket_usd_per_ticket', $competition->ticket_usd_per_ticket) }}">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>@lang('Max Tickets Per User')</label>
                <input type="number" min="0" class="form-control" name="max_tickets_per_user" value="{{ old('max_tickets_per_user', $competition->max_tickets_per_user) }}">
            </div>
        </div>

        <div class="col-md-12"><p class="text-muted mb-2">@lang('Table League points per result')</p></div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Points for a Win')</label>
                <input type="number" step="0.01" class="form-control" name="points_win" value="{{ old('points_win', $pointsConfig['win'] ?? 3) }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Points for a Draw')</label>
                <input type="number" step="0.01" class="form-control" name="points_draw" value="{{ old('points_draw', $pointsConfig['draw'] ?? 1) }}">
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>@lang('Points for a Loss')</label>
                <input type="number" step="0.01" class="form-control" name="points_loss" value="{{ old('points_loss', $pointsConfig['loss'] ?? 0) }}">
            </div>
        </div>
    </div>
    </fieldset>

    <div class="col-md-4">
        <div class="form-group">
            <label>@lang('Grace Period (minutes)')</label>
            <input type="number" min="0" max="1440" class="form-control" name="grace_minutes" value="{{ old('grace_minutes', $competition->grace_minutes ?: 30) }}" required>
            <small class="text-muted">@lang('Wait time after Ends At, plus one more sweep, before finalizing.')</small>
        </div>
    </div>

    <div class="col-md-12"><hr><h6 class="mb-3">@lang('Prizes')</h6></div>
    <div class="col-md-12">
        <div class="form-group">
            <label>@lang('One prize per line: rank,amount_usd')</label>
            <textarea class="form-control" name="prizes_raw" rows="5" placeholder="1,500&#10;2,250&#10;3,100" {{ $competition->exists && $competition->hasAnyPaidResult() ? 'disabled' : '' }}>{{ old('prizes_raw', $competition->exists ? $competition->prizes->map(fn($p) => $p->rank . ',' . $p->amount_usd)->implode("\n") : '') }}</textarea>
            @if ($competition->exists && $competition->hasAnyPaidResult())
                <small class="text-warning">@lang('Prizes are locked - at least one has already been paid.')</small>
            @else
                <small class="text-muted">@lang('Leave blank to keep existing prizes unchanged.')</small>
            @endif
        </div>
    </div>
</div>
