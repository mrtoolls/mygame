@extends('admin.layouts.app')

@section('panel')
<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h5 class="card-title mb-0">@lang('Create Contest')</h5></div>
            <form action="{{ route('admin.fantasy.contests.store') }}" method="POST" class="disableSubmission">
                @csrf
                <div class="card-body">
                    <div class="form-group">
                        <label>@lang('Gameweek')</label>
                        <select name="gameweek_id" class="form-control" required>
                            @foreach ($openGameweeks as $gw)<option value="{{ $gw->id }}">GW{{ $gw->number }} ({{ $gw->status }})</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>@lang('Name')</label>
                        <input type="text" class="form-control" name="name" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label>@lang('Type')</label>
                        <select name="type" class="form-control" required>
                            <option value="h2h">@lang('Head-to-Head')</option>
                            <option value="gameweek">@lang('Gameweek Pool')</option>
                            <option value="quick">@lang('Quick Contest (separate squad)')</option>
                            <option value="sponsored">@lang('Sponsored (free)')</option>
                        </select>
                    </div>
                    <div class="form-group" id="quickBudgetTier">
                        <label>@lang('Quick Contest Budget Tier (fantasy credits)')</label>
                        <select name="budget_tier" class="form-control">
                            @foreach ($budgetTiers as $tier)@php($tierKey = rtrim(rtrim(number_format($tier, 1, '.', ''), '0'), '.'))<option value="{{ $tier }}">{{ number_format($tier, 1) }} @lang('credits') — @lang('minimum entry') {{ number_format($minimumFees[$tierKey] ?? 0, 2) }} @lang('coins')</option>@endforeach
                        </select>
                        <small class="text-muted">@lang('Every entrant gets the same squad budget at this tier. No budget can be purchased with coins.')</small>
                    </div>
                    <div class="form-group">
                        <label>@lang('Entry Fee (coins, ignored for sponsored)')</label>
                        <input type="number" step="0.00000001" class="form-control" name="entry_fee" value="0">
                        <small class="text-muted">@lang('Quick contests must meet the selected tier minimum.')</small>
                    </div>
                    <div class="form-group">
                        <label>@lang('Sponsored Pool (sponsored only)')</label>
                        <input type="number" step="0.00000001" class="form-control" name="sponsored_pool">
                    </div>
                    <div class="form-group">
                        <label>@lang('Min Entrants')</label>
                        <input type="number" class="form-control" name="min_entrants" value="2" min="2">
                    </div>
                    <div class="form-group">
                        <label>@lang('Max Entrants (leave blank for H2H = 2)')</label>
                        <input type="number" class="form-control" name="max_entrants" min="2">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn--primary w-100">@lang('Create')</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body table-responsive">
                <table class="table">
                    <thead><tr><th>@lang('Name')</th><th>@lang('Type / Budget')</th><th>@lang('GW')</th><th>@lang('Status')</th><th>@lang('Pool')</th><th>@lang('Fee')</th></tr></thead>
                    <tbody>
                        @foreach ($contests as $contest)
                            <tr>
                                <td>{{ $contest->name }}</td>
                                <td>{{ $contest->type }}@if($contest->type === 'quick')<br><small>{{ number_format($contest->budget_tier, 1) }} @lang('credits')</small>@endif</td>
                                <td>{{ $contest->gameweek->number ?? '-' }}</td>
                                <td><span class="badge bg-secondary">{{ $contest->status }}</span></td>
                                <td>{{ $contest->final_pool !== null ? number_format($contest->final_pool, 2) : '-' }}</td>
                                <td>{{ $contest->final_fee_burned !== null ? number_format($contest->final_fee_burned, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                {{ $contests->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.fantasy.index') }}" />
@endpush
