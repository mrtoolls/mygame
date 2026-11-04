@extends('admin.layouts.app')

@section('panel')
<div class="card">
    <form action="{{ route('admin.fantasy.settings.update') }}" method="POST" class="disableSubmission">
        @csrf
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3 form-check">
                    <input type="checkbox" class="form-check-input" name="ff_enabled" id="ff_enabled" value="1" @checked(gs('ff_enabled'))>
                    <label class="form-check-label" for="ff_enabled">@lang('Fantasy Football Enabled')</label>
                </div>
                <div class="col-md-6 mb-3 form-check">
                    <input type="checkbox" class="form-check-input" name="ff_price_change_enabled" id="ff_price_change_enabled" value="1" @checked(gs('ff_price_change_enabled'))>
                    <label class="form-check-label" for="ff_price_change_enabled">@lang('Daily Price Changes Enabled')</label>
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Match Duration (minutes)')</label>
                    <input type="number" class="form-control" name="ff_match_duration_minutes" value="{{ gs('ff_match_duration_minutes') }}" min="1" max="60">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Gameweek Cadence (days)')</label>
                    <input type="number" class="form-control" name="ff_gameweek_cadence_days" value="{{ gs('ff_gameweek_cadence_days') }}" min="1" max="7">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Settlement Review Window (minutes)')</label>
                    <input type="number" class="form-control" name="ff_settlement_review_minutes" value="{{ gs('ff_settlement_review_minutes') }}" min="0" max="1440">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Contest Fee (%)')</label>
                    <input type="number" step="0.01" class="form-control" name="ff_contest_fee_percent" value="{{ gs('ff_contest_fee_percent') }}" min="0" max="100">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Price Change Step')</label>
                    <input type="number" step="0.1" class="form-control" name="ff_price_change_step" value="{{ gs('ff_price_change_step') }}" min="0.1" max="5">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Min Player Price')</label>
                    <input type="number" step="0.1" class="form-control" name="ff_price_min" value="{{ gs('ff_price_min') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Max Player Price')</label>
                    <input type="number" step="0.1" class="form-control" name="ff_price_max" value="{{ gs('ff_price_max') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Max Stake Per Entry')</label>
                    <input type="number" step="0.01" class="form-control" name="ff_max_entry_stake" value="{{ gs('ff_max_entry_stake') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label>@lang('Daily Stake Cap Per User')</label>
                    <input type="number" step="0.01" class="form-control" name="ff_daily_stake_cap" value="{{ gs('ff_daily_stake_cap') }}">
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn--primary w-100">@lang('Save Settings')</button>
        </div>
    </form>
</div>
@endsection

@push('breadcrumb-plugins')
<x-back route="{{ route('admin.fantasy.index') }}" />
@endpush
