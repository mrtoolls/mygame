@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.gift.store') }}" class="disableSubmission" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Gift Name')</label>
                                    <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="@lang('e.g. Rose, Crown, Diamond')" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Sort Order')</label>
                                    <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', 0) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Buy Price')</label>
                                    <div class="input-group">
                                        <div class="input-group-text">{{ gs('coin_mode') ? __('Coins') : '$' }}</div>
                                        <input type="number" step="any" min="0.00000001" class="form-control" name="buy_price" value="{{ old('buy_price') }}" required>
                                    </div>
                                    <small class="text-muted">{{ gs('coin_mode') ? __('Users pay this price from their available coin wallet.') : __('Priced in USD - converted to each user\'s own wallet currency automatically.') }}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Cash-out Value')</label>
                                    <div class="input-group">
                                        <div class="input-group-text">{{ gs('coin_mode') ? __('Coins') : '$' }}</div>
                                        <input type="number" step="any" min="0" class="form-control" name="cashout_value" value="{{ old('cashout_value') }}" required>
                                    </div>
                                    <small class="text-muted">{{ gs('coin_mode') ? __('Coins returned to the coin wallet on cash-out; must not exceed the buy price.') : __('What a recipient gets credited when they cash this gift out - must not exceed the buy price.') }}</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Gift Image')</label>
                                    <input type="file" class="form-control" name="image" accept="image/png,image/jpeg,image/webp" required>
                                    <small class="text-muted">@lang('A square 2D icon image, e.g. 240x240.')</small>
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
    <x-back route="{{ route('admin.gift.index') }}" />
@endpush
