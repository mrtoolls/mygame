@extends('admin.layouts.app')
@section('panel')
    @php
        $global = $settings->get('*');
        $value = fn ($key, $field, $default) => data_get($settings->get($key), $field, data_get($global, $field, $default));
        $defaultGuide = fn ($key) => \App\Games\TableGames\TableGameGuides::for($key);
        $guideValue = function ($key, $field) use ($settings, $defaultGuide) {
            $saved = data_get($settings->get($key)?->guide_json, $field);
            $value = $saved !== null && $saved !== '' && $saved !== [] ? $saved : data_get($defaultGuide($key), $field);
            return is_array($value) ? implode("\n", $value) : $value;
        };
        $definitions = ['*' => ['name' => 'Global defaults', 'min' => 2, 'max' => 4]] + $gameDefinitions;
    @endphp
    <div class="row"><div class="col-12"><div class="card"><div class="card-body">
        <h5 class="mb-1">@lang('Live Table Controls')</h5><p class="text-muted mb-3">@lang('Configure fair player-versus-player tables. The server validates every move; administrators cannot choose game outcomes. These controls affect table access, timing and wallet settlement only.')</p>
        <form action="{{ route('admin.table-games.settings') }}" method="post" enctype="multipart/form-data">@csrf
            @foreach ($definitions as $key => $definition)
                <details class="table-admin-setting" @if ($key === '*') open @endif>
                    <summary><strong>{{ $definition['name'] }}</strong><span>{{ $key === '*' ? 'Global defaults' : $definition['min'] . '–' . $definition['max'] . ' players · ' . (in_array($key, $availableGames, true) ? 'Rules ready' : 'Coming soon') }}</span></summary>
                    <div class="table-admin-grid">
                        <label><span>@lang('Enabled')</span><input type="hidden" name="settings[{{ $key }}][enabled]" value="0"><input type="checkbox" name="settings[{{ $key }}][enabled]" value="1" @checked($value($key, 'enabled', true))></label>
                        <label><span>@lang('Commission %')</span><input type="number" name="settings[{{ $key }}][commission_percent]" min="0" max="100" step="0.01" value="{{ $value($key, 'commission_percent', 5) }}" required></label>
                        <label><span>@lang('Minimum stake')</span><input type="number" name="settings[{{ $key }}][min_stake]" min="0.00000001" step="any" value="{{ $value($key, 'min_stake', 1) }}" required></label>
                        <label><span>@lang('Maximum stake')</span><input type="number" name="settings[{{ $key }}][max_stake]" min="0.00000001" step="any" value="{{ $value($key, 'max_stake', 1000) }}" required></label>
                        <label><span>@lang('Turn timer (seconds)')</span><input type="number" name="settings[{{ $key }}][turn_seconds]" min="10" max="3600" value="{{ $value($key, 'turn_seconds', 60) }}" required></label>
                        <p class="text-muted mb-0">@lang('Waiting tables automatically finish after 24 hours. Any unsettled player stakes are refunded to their wallets.')</p>
                        <label><span>@lang('Open tables per player')</span><input type="number" name="settings[{{ $key }}][max_open_tables_per_user]" min="1" max="100" value="{{ $value($key, 'max_open_tables_per_user', 5) }}" required></label>
                        <label><span>@lang('Maximum concurrent tables')</span><input type="number" name="settings[{{ $key }}][max_concurrent_tables]" min="1" max="100000" value="{{ $value($key, 'max_concurrent_tables', 500) }}" required></label>
                        @if ($key !== '*')
                            <label class="table-game-image-setting"><span>@lang('Live game card image')</span>
                                @if ($settings->get($key)?->card_image)
                                    <img src="{{ getImage(getFilePath('game') . '/' . $settings->get($key)->card_image, getFileSize('game')) }}" alt="{{ __($definition['name']) }} @lang('card image')">
                                @else
                                    <small>@lang('No image uploaded yet')</small>
                                @endif
                                <input type="file" name="game_images[{{ $key }}]" accept="image/jpeg,image/png,image/webp">
                                <small>@lang('Upload a JPG, PNG or WebP image (max 5 MB).')</small>
                            </label>
                        @endif
                    </div>
                    @if ($key !== '*')
                        <div class="table-admin-guide">
                            <p class="text-muted mb-2">@lang('Shown to players on the (i) How to Play sheet for this game. Leave any field blank to use the built-in default text.')</p>
                            <div class="table-admin-grid">
                                <label><span>@lang('Goal')</span><textarea name="settings[{{ $key }}][guide][goal]" rows="2">{{ $guideValue($key, 'goal') }}</textarea></label>
                                <label><span>@lang('Setup')</span><textarea name="settings[{{ $key }}][guide][setup]" rows="2">{{ $guideValue($key, 'setup') }}</textarea></label>
                                <label class="table-admin-guide__wide"><span>@lang('How to play (one step per line)')</span><textarea name="settings[{{ $key }}][guide][how_to_play]" rows="4">{{ $guideValue($key, 'how_to_play') }}</textarea></label>
                                <label class="table-admin-guide__wide"><span>@lang('Special rules')</span><textarea name="settings[{{ $key }}][guide][special_rules]" rows="3">{{ $guideValue($key, 'special_rules') }}</textarea></label>
                                <label><span>@lang('How a draw works')</span><textarea name="settings[{{ $key }}][guide][draw]" rows="2">{{ $guideValue($key, 'draw') }}</textarea></label>
                                <label><span>@lang('How a win is decided')</span><textarea name="settings[{{ $key }}][guide][win]" rows="2">{{ $guideValue($key, 'win') }}</textarea></label>
                            </div>
                        </div>
                    @endif
                </details>
            @endforeach
            <button class="btn btn--primary w-100 mt-3" type="submit">@lang('Save Table Settings')</button>
        </form>
    </div></div></div></div>

    <div class="row mt-4"><div class="col-xl-4 col-lg-5"><div class="card h-100"><div class="card-body"><h5>@lang('Commission Summary')</h5><p class="text-muted">@lang('Retained commission is calculated from each completed table pot.')</p><div class="d-flex justify-content-between py-2 border-bottom"><span>@lang('Total retained')</span><strong>{{ gs('cur_sym') }}{{ showAmount($commissionTotal) }}</strong></div>@foreach ($commissionByGame as $summary)<div class="d-flex justify-content-between py-2 border-bottom"><span>{{ __($gameDefinitions[$summary->game_type]['name'] ?? $summary->game_type) }}</span><strong>{{ gs('cur_sym') }}{{ showAmount($summary->total) }} <small class="text-muted">({{ $summary->settled_tables }})</small></strong></div>@endforeach</div></div></div>
        <div class="col-xl-8 col-lg-7"><div class="card"><div class="card-body"><h5>@lang('Player Table Access')</h5><p class="text-muted">@lang('Use a user ID from Manage Users to restrict table access or set a personal open-table cap.')</p><form method="post" action="{{ route('admin.table-games.user-limit') }}">@csrf<div class="row align-items-end"><div class="col-md-3"><label class="form-label">@lang('User ID')</label><input class="form-control" type="number" name="user_id" required min="1"></div><div class="col-md-3"><label class="form-label">@lang('Max open tables')</label><input class="form-control" type="number" name="max_open_tables" min="1" max="100" placeholder="@lang('Use global')"></div><div class="col-md-3"><label class="form-label">@lang('Restriction')</label><select class="form-control" name="banned"><option value="0">@lang('Allowed')</option><option value="1">@lang('Blocked from tables')</option></select></div><div class="col-md-3"><button class="btn btn--primary w-100" type="submit">@lang('Save player setting')</button></div><div class="col-12 mt-2"><input class="form-control" name="note" maxlength="255" placeholder="@lang('Admin note (optional)')"></div></div></form></div></div></div></div>

    <div class="row mt-4"><div class="col-12"><div class="card"><div class="card-body"><h5>@lang('Live Tables')</h5><div class="table-responsive"><table class="table table--responsive--md"><thead><tr><th>ID</th><th>@lang('Game')</th><th>@lang('Creator')</th><th>@lang('Seats')</th><th>@lang('Stake')</th><th>@lang('Status')</th><th>@lang('Action')</th></tr></thead><tbody>@forelse ($liveTables as $table)<tr><td><code>{{ Str::limit($table->id, 12) }}</code></td><td>{{ __($gameDefinitions[$table->game_type]['name'] ?? $table->game_type) }}</td><td>{{ $table->creator?->username }}</td><td>{{ $table->players->where('status','seated')->count() }}/{{ $table->player_limit }}</td><td>{{ gs('cur_sym') }}{{ showAmount($table->stake) }}</td><td><span class="badge badge--warning">{{ __(ucfirst($table->status)) }}</span></td><td><form method="post" action="{{ route('admin.table-games.force-close', $table->id) }}">@csrf<button type="submit" class="btn btn-sm btn--danger">@lang('Close & refund')</button></form></td></tr>@empty<tr><td colspan="7" class="text-center">@lang('No live tables')</td></tr>@endforelse</tbody></table></div>{{ $liveTables->appends(request()->except('live_page'))->links() }}</div></div></div></div>

    <div class="row mt-4"><div class="col-12"><div class="card"><div class="card-body"><h5>@lang('Table History')</h5><div class="table-responsive"><table class="table table--responsive--md"><thead><tr><th>@lang('Table')</th><th>@lang('Game')</th><th>@lang('Players')</th><th>@lang('Pot')</th><th>@lang('Result')</th><th>@lang('Finished')</th></tr></thead><tbody>@forelse ($history as $table)<tr><td><code>{{ Str::limit($table->id, 12) }}</code></td><td>{{ __($gameDefinitions[$table->game_type]['name'] ?? $table->game_type) }}</td><td>{{ $table->players()->count() }}</td><td>{{ gs('cur_sym') }}{{ showAmount($table->stake * $table->players()->count()) }}</td><td>{{ __($table->result ?? $table->status) }}</td><td>{{ showDateTime($table->finished_at) }}</td></tr>@empty<tr><td colspan="6" class="text-center">@lang('No completed tables yet')</td></tr>@endforelse</tbody></table></div>{{ $history->appends(request()->except('history_page'))->links() }}</div></div></div></div>
    <style>.table-admin-setting{margin:10px 0;border:1px solid rgba(145,156,205,.18);border-radius:10px;background:rgba(35,42,71,.2)}.table-admin-setting summary{display:flex;justify-content:space-between;gap:10px;padding:13px;cursor:pointer}.table-admin-setting summary span{color:#858eb4;font-size:12px}.table-admin-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:0 13px 14px}.table-admin-grid label{display:grid;gap:6px;font-size:11px}.table-admin-grid input:not([type=checkbox]){width:100%;height:38px;padding:0 9px;border:1px solid rgba(145,156,205,.2);border-radius:7px;background:#10142a;color:#fff}.table-admin-grid label:has(input[type=checkbox]){display:flex;align-items:center;gap:10px}.table-admin-grid input[type=checkbox]{width:18px;height:18px;accent-color:#00df82}.table-game-image-setting{grid-column:1/-1;max-width:360px;padding:12px;border:1px solid rgba(145,156,205,.18);border-radius:10px;background:#10142a}.table-game-image-setting img{width:100%;max-height:160px;object-fit:cover;border-radius:8px}.table-game-image-setting input[type=file]{height:auto!important;padding:8px!important}.table-game-image-setting small{color:#858eb4}@media(max-width:767px){.table-admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    .table-admin-guide{margin:0 13px 14px;padding:12px;border:1px solid rgba(145,156,205,.14);border-radius:10px;background:rgba(16,20,42,.4)}.table-admin-guide .table-admin-grid{padding:0}.table-admin-guide textarea{width:100%;padding:8px 9px;border:1px solid rgba(145,156,205,.2);border-radius:7px;background:#10142a;color:#fff;font:inherit;font-size:12px;resize:vertical}.table-admin-guide__wide{grid-column:1/-1}</style>
@endsection
