@extends('admin.layouts.app')

@section('panel')
<div class="row mb-3">
    @foreach ([['Seasons', $stats['seasons']], ['Clubs', $stats['clubs']], ['Players', $stats['players']], ['Fixtures', $stats['fixtures']], ['Teams', $stats['teams']], ['Open Contests', $stats['open_contests']]] as [$label, $value])
        <div class="col-md-2 col-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <h4>{{ number_format($value) }}</h4>
                    <p class="mb-0">@lang($label)</p>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">@lang('Generate League')</h5></div>
            <form action="{{ route('admin.fantasy.generate') }}" method="POST" class="disableSubmission">
                @csrf
                <div class="card-body">
                    @if ($season)
                        <div class="alert alert--warning">@lang('A season already exists'): <strong>{{ $season->name }}</strong>. @lang('Generating a new one adds a separate season and does not affect the existing one.')</div>
                    @endif
                    <div class="form-group">
                        <label>@lang('Season Name')</label>
                        <input type="text" class="form-control" name="name" required placeholder="@lang('e.g. Season One')">
                    </div>
                    <div class="form-group">
                        <label>@lang('First Kickoff')</label>
                        <input type="datetime-local" class="form-control" name="first_kickoff" required>
                    </div>
                    <div class="form-group">
                        <label>@lang('Gameweek Cadence (days)')</label>
                        <input type="number" class="form-control" name="cadence_days" min="1" max="7" value="1">
                    </div>
                    <small class="text-muted">@lang('Generates 20 fictional clubs, ~26 fictional players each, and a full double round-robin season (38 gameweeks, 380 fixtures).')</small>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary w-100">@lang('Generate League')</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">@lang('Quick Links')</h5></div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('admin.fantasy.clubs') }}" class="btn btn-outline-secondary">@lang('Clubs')</a>
                <a href="{{ route('admin.fantasy.players') }}" class="btn btn-outline-secondary">@lang('Players')</a>
                <a href="{{ route('admin.fantasy.scoring') }}" class="btn btn-outline-secondary">@lang('Scoring Rules')</a>
                <a href="{{ route('admin.fantasy.contests') }}" class="btn btn-outline-secondary">@lang('Contests')</a>
                <a href="{{ route('admin.fantasy.stat-corrections') }}" class="btn btn-outline-secondary">@lang('Stat Corrections')</a>
                <a href="{{ route('admin.fantasy.settings') }}" class="btn btn-outline-secondary">@lang('Settings')</a>
                <a href="{{ route('admin.fantasy.audit-log') }}" class="btn btn-outline-secondary">@lang('Audit Log')</a>
                <form action="{{ route('admin.fantasy.kill-switch') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn w-100 {{ gs('ff_enabled') ? 'btn-danger' : 'btn-success' }}">
                        {{ gs('ff_enabled') ? __('Disable Fantasy Football (Kill Switch)') : __('Re-enable Fantasy Football') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
