@extends('admin.layouts.app')
@section('panel')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h5>@lang('Read-only money conversion audit')</h5>
                    <p class="text-muted mb-0">@lang('This report only reads the database. It does not round, convert, or update balances. Fractional precision issues count values that cannot be represented at the assigned currency precision.')</p>
                    <small class="text-muted">@lang('Checked at') {{ $report['checked_at']->toDateTimeString() }}</small>
                </div>
                <a class="btn btn-outline--dark" href="{{ route('admin.currency-country.index') }}">@lang('Back to currencies')</a>
            </div>

            <div class="row g-3 my-3">
                <div class="col-sm-4"><div class="card border"><div class="card-body"><small>@lang('Amount-column observations')</small><h4>{{ number_format($report['total_rows']) }}</h4></div></div></div>
                <div class="col-sm-4"><div class="card border"><div class="card-body"><small>@lang('Values without currency assignment')</small><h4 class="{{ $report['unassigned_rows'] ? 'text--danger' : 'text--success' }}">{{ number_format($report['unassigned_rows']) }}</h4></div></div></div>
                <div class="col-sm-4"><div class="card border"><div class="card-body"><small>@lang('Values exceeding currency decimals')</small><h4 class="{{ $report['precision_issue_rows'] ? 'text--danger' : 'text--success' }}">{{ number_format($report['precision_issue_rows']) }}</h4></div></div></div>
            </div>

            @if($report['missing_tables'])
                <div class="alert alert-warning">@lang('Some optional money tables were not present:') {{ implode(', ', $report['missing_tables']) }}</div>
            @endif

            <div class="alert alert-info">
                <strong>@lang('Conversion gate')</strong>
                <p class="mb-0">@lang('Do not switch wallet reads or writes to integer minor units until each listed amount field is mapped, unassigned and over-precision records are resolved, and wallet balances reconcile with ledger and gateway totals on a database copy. This audit is a preflight report, not approval to run a conversion migration.')</p>
            </div>

            <div class="table-responsive"><table class="table table--light style--two">
                <thead><tr><th>@lang('Table')</th><th>@lang('Amount field')</th><th>@lang('Current database type')</th><th>@lang('Rows')</th><th>@lang('Nonzero')</th><th>@lang('Currency missing')</th><th>@lang('Precision issues')</th></tr></thead>
                <tbody>
                    @forelse($report['fields'] as $field)
                        <tr><td>{{ $field['table'] }}</td><td>{{ $field['column'] }}</td><td><code>{{ $field['database_type'] }}</code></td><td>{{ number_format($field['rows']) }}</td><td>{{ number_format($field['nonzero_rows']) }}</td><td>{{ $field['currency_unassigned'] === null ? __('No currency column') : number_format($field['currency_unassigned']) }}</td><td>{{ $field['fractional_precision_issues'] === null ? __('No currency column') : number_format($field['fractional_precision_issues']) }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center">@lang('No supported amount columns were found.')</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
@endsection
