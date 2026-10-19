@extends('admin.layouts.app')
@section('panel')
<div class="alert alert-info">@lang('Set the charge for one day, one week and one month in each wallet currency. Values are stored in the currency minor units. A price of zero disables that campaign length for users in that currency.')</div>
<form method="post" action="{{ route('admin.business-promotions.rates.save') }}">
    @csrf
    <div class="card"><div class="card-body p-0"><div class="table-responsive"><table class="table table--light style--two">
        <thead><tr><th>@lang('Currency')</th><th>@lang('Daily')</th><th>@lang('Weekly')</th><th>@lang('Monthly')</th></tr></thead>
        <tbody>
        @foreach($currencies as $index => $currency)
            @php $rate = $rates->get($currency->id); @endphp
            <tr>
                <td><strong>{{ $currency->code }}</strong><br><small>{{ $currency->name }} · {{ $currency->enabled ? __('Enabled') : __('Disabled') }}</small><input type="hidden" name="prices[{{ $index }}][currency_id]" value="{{ $currency->id }}"></td>
                <td><div class="input-group"><span class="input-group-text">{{ $currency->symbol }}</span><input class="form-control" type="number" min="0" step="{{ $currency->decimals ? '0.' . str_repeat('0', max($currency->decimals - 1, 0)) . '1' : '1' }}" name="prices[{{ $index }}][day]" value="{{ app(\App\Support\Money::class)->formatMinor($rate?->daily_price_minor ?? 0, $currency->decimals, false) }}"></div></td>
                <td><div class="input-group"><span class="input-group-text">{{ $currency->symbol }}</span><input class="form-control" type="number" min="0" step="{{ $currency->decimals ? '0.' . str_repeat('0', max($currency->decimals - 1, 0)) . '1' : '1' }}" name="prices[{{ $index }}][week]" value="{{ app(\App\Support\Money::class)->formatMinor($rate?->weekly_price_minor ?? 0, $currency->decimals, false) }}"></div></td>
                <td><div class="input-group"><span class="input-group-text">{{ $currency->symbol }}</span><input class="form-control" type="number" min="0" step="{{ $currency->decimals ? '0.' . str_repeat('0', max($currency->decimals - 1, 0)) . '1' : '1' }}" name="prices[{{ $index }}][month]" value="{{ app(\App\Support\Money::class)->formatMinor($rate?->monthly_price_minor ?? 0, $currency->decimals, false) }}"></div></td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div></div>
    <button class="btn btn--primary w-100 mt-3" type="submit">@lang('Save prices')</button>
</form>
@endsection
