@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0">@lang('Choose a Fixture')</h5></div>
            <div class="card-body">
                <form method="GET">
                    <select name="fixture_id" class="form-control" onchange="this.form.submit()">
                        <option value="">@lang('Select a final fixture')</option>
                        @foreach ($recentFixtures as $f)
                            <option value="{{ $f->id }}" @selected($fixture && $fixture->id === $f->id)>{{ $f->homeClub->short_code }} {{ $f->home_score }}-{{ $f->away_score }} {{ $f->awayClub->short_code }} (GW{{ $f->gameweek_id }})</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        @if ($fixture)
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">@lang('Correct a Stat')</h5></div>
                <form action="{{ route('admin.fantasy.stat-corrections.store') }}" method="POST" class="disableSubmission">
                    @csrf
                    <input type="hidden" name="fixture_id" value="{{ $fixture->id }}">
                    <div class="card-body">
                        <div class="form-group">
                            <label>@lang('Player')</label>
                            <select name="player_id" class="form-control" required>
                                @foreach ($stats as $stat)<option value="{{ $stat->player_id }}">{{ $stat->player->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Field')</label>
                            <select name="field" class="form-control" required>
                                @foreach (['minutes', 'goals', 'assists', 'yellow_cards', 'red_cards', 'own_goals', 'penalties_saved', 'penalties_missed', 'saves', 'goals_conceded', 'bonus'] as $field)
                                    <option value="{{ $field }}">{{ ucfirst(str_replace('_', ' ', $field)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('New Value')</label>
                            <input type="number" class="form-control" name="value" min="0" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Reason')</label>
                            <input type="text" class="form-control" name="reason" maxlength="255">
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn--primary w-100">@lang('Apply Correction')</button>
                        <small class="text-muted d-block mt-2">@lang('Refused once this gameweek\'s contests have already paid out.')</small>
                    </div>
                </form>
            </div>
        @endif
    </div>
    <div class="col-lg-7">
        @if ($fixture)
            <div class="card mb-3">
                <div class="card-body table-responsive">
                    <h6>{{ $fixture->homeClub->name }} {{ $fixture->home_score }}-{{ $fixture->away_score }} {{ $fixture->awayClub->name }}</h6>
                    <table class="table table-sm">
                        <thead><tr><th>@lang('Player')</th><th>Min</th><th>G</th><th>A</th><th>YC</th><th>RC</th><th>Pts</th></tr></thead>
                        <tbody>
                            @foreach ($stats as $stat)
                                <tr><td>{{ $stat->player->name }}</td><td>{{ $stat->minutes }}</td><td>{{ $stat->goals }}</td><td>{{ $stat->assists }}</td><td>{{ $stat->yellow_cards }}</td><td>{{ $stat->red_cards }}</td><td><strong>{{ $stat->total_points }}</strong></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">@lang('Recent Corrections')</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>@lang('Fixture')</th><th>@lang('Field')</th><th>@lang('Old')</th><th>@lang('New')</th><th>@lang('When')</th></tr></thead>
                    <tbody>
                        @foreach ($log as $entry)
                            <tr><td>#{{ $entry->fixture_id }}</td><td>{{ $entry->field }}</td><td>{{ $entry->old_value }}</td><td>{{ $entry->new_value }}</td><td>{{ $entry->created_at }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.fantasy.index') }}" />
@endpush
