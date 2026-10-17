@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.vip.store') }}" class="disableSubmission" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Tier Name')</label>
                                    <input type="text" class="form-control" name="name" value="{{ old('name') }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Badge Text (optional)')</label>
                                    <input type="text" class="form-control" name="badge" value="{{ old('badge') }}" placeholder="@lang('e.g. Bronze')">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Minimum Lifetime Wagered to Unlock')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="min_wagered" value="{{ old('min_wagered', 0) }}" required>
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('One-time Bonus on Unlock')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="bonus_amount" value="{{ old('bonus_amount', 0) }}" required>
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Daily Rakeback')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" max="100" class="form-control" name="rakeback_percent" value="{{ old('rakeback_percent', 0) }}" required>
                                        <div class="input-group-text">%</div>
                                    </div>
                                    <small class="text-muted">@lang('Percent of lifetime wagering a user at this tier can claim back')</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Max Bet Limit (optional)')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="max_bet" value="{{ old('max_bet') }}">
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Sort Order')</label>
                                    <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', 0) }}">
                                    <small class="text-muted">@lang('Lowest shows first (e.g. Bronze = 0, Gold = 4)')</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn--primary w-100 h-45">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.vip.index') }}" />
@endpush
