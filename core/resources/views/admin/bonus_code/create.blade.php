@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.bonus.code.store') }}" class="disableSubmission" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Code')</label>
                                    <input type="text" class="form-control text-uppercase" name="code" value="{{ old('code') }}" placeholder="@lang('e.g. WELCOME50')" required>
                                </div>
                            </div>
                            @if($currencies->isNotEmpty())
                                <div class="col-md-6"><div class="form-group"><label>@lang('Wallet Currency')</label><select class="form-control" name="currency_id" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}" @selected(old('currency_id') == $currency->id)>{{ $currency->code }} · {{ $currency->name }}</option>@endforeach</select></div></div>
                            @endif
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Bonus Amount')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="amount" value="{{ old('amount') }}" required>
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Max Uses (optional)')</label>
                                    <input type="number" min="1" class="form-control" name="max_uses" value="{{ old('max_uses') }}" placeholder="@lang('Unlimited if left blank')">
                                    <small class="text-muted">@lang('Total times this code can be redeemed across all users (each user can only use it once)')</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Expires At (optional)')</label>
                                    <input type="datetime-local" class="form-control" name="expires_at" value="{{ old('expires_at') }}">
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
    <x-back route="{{ route('admin.bonus.code.index') }}" />
@endpush
