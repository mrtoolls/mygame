@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="{{ route('admin.gift.update', $gift->id) }}" class="disableSubmission" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12 text-center">
                                <img src="{{ $gift->imageUrl() }}" alt="{{ $gift->name }}" width="90" height="90" style="object-fit:contain" class="mb-3">
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Gift Name')</label>
                                    <input type="text" class="form-control" name="name" value="{{ old('name', $gift->name) }}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Sort Order')</label>
                                    <input type="number" class="form-control" name="sort_order" value="{{ old('sort_order', $gift->sort_order) }}">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Buy Price')</label>
                                    <div class="input-group">
                                        <div class="input-group-text">{{ gs('coin_mode') ? __('Coins') : '$' }}</div>
                                        <input type="number" step="any" min="0.00000001" class="form-control" name="buy_price" value="{{ old('buy_price', gs('coin_mode') ? bcdiv((string) $gift->buy_price, (string) (gs('coin_peg_usd') ?: '0.01'), 0) : $gift->buy_price) }}" required>
                                    </div>
                                    <small class="text-muted">{{ gs('coin_mode') ? __('Users pay this price from their available coin wallet.') : __('Priced in USD - converted to each user\'s own wallet currency automatically.') }}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Cash-out Value')</label>
                                    <div class="input-group">
                                        <div class="input-group-text">{{ gs('coin_mode') ? __('Coins') : '$' }}</div>
                                        <input type="number" step="any" min="0" class="form-control" name="cashout_value" value="{{ old('cashout_value', gs('coin_mode') ? bcdiv((string) $gift->cashout_value, (string) (gs('coin_peg_usd') ?: '0.01'), 0) : $gift->cashout_value) }}" required>
                                    </div>
                                    <small class="text-muted">{{ gs('coin_mode') ? __('Coins returned to the coin wallet on cash-out; must not exceed the buy price.') : __('Must not exceed the buy price.') }}</small>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>@lang('Replace Gift Image (optional)')</label>
                                    <input type="file" class="form-control" name="image" accept="image/png,image/jpeg,image/webp">
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
