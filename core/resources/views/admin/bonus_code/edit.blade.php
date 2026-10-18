@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.bonus.code.update', $code->id) }}" class="disableSubmission" method="POST">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Code')</label>
                                    <input type="text" class="form-control text-uppercase" name="code" value="{{ old('code', $code->code) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Bonus Amount')</label>
                                    <div class="input-group">
                                        <input type="number" step="any" min="0" class="form-control" name="amount" value="{{ old('amount', $code->amount) }}" required>
                                        <div class="input-group-text">{{ __(gs('cur_text')) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Max Uses (optional)')</label>
                                    <input type="number" min="1" class="form-control" name="max_uses" value="{{ old('max_uses', $code->max_uses) }}" placeholder="@lang('Unlimited if left blank')">
                                    <small class="text-muted">@lang('Already used :count time(s)', ['count' => $code->used_count])</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Expires At (optional)')</label>
                                    <input type="datetime-local" class="form-control" name="expires_at" value="{{ old('expires_at', $code->expires_at?->format('Y-m-d\TH:i')) }}">
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
