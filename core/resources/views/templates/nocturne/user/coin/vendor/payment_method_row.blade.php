@php($type = $method['type'] ?? 'bank')
<div class="coin-pm-row" data-type="{{ $type }}">
    <div class="coin-pm-head">
        <div class="coin-field">
            <label>@lang('Type')</label>
            <select class="coin-pm-type" name="payment_methods[{{ $index }}][type]">
                <option value="bank" @selected($type === 'bank')>@lang('Bank transfer')</option>
                <option value="mobile_money" @selected($type === 'mobile_money')>@lang('Mobile money')</option>
                <option value="crypto" @selected($type === 'crypto')>@lang('Crypto')</option>
                <option value="other" @selected($type === 'other')>@lang('Other')</option>
            </select>
        </div>
        <div class="coin-field coin-field--grow">
            <label>@lang('Display name') <small>@lang('Optional')</small></label>
            <input name="payment_methods[{{ $index }}][label]" placeholder="@lang('e.g. Opay NG')" value="{{ $method['label'] ?? '' }}" maxlength="120">
        </div>
        <button type="button" class="coin-pm-remove" aria-label="@lang('Remove payment method')"><i class="ph-bold ph-trash"></i></button>
    </div>

    <div class="coin-pm-fields" data-fields="bank">
        <div class="coin-field"><label>@lang('Bank name')</label><input name="payment_methods[{{ $index }}][bank_name]" value="{{ $method['bank_name'] ?? '' }}" maxlength="160" @disabled($type !== 'bank')></div>
        <div class="coin-field"><label>@lang('Account number')</label><input name="payment_methods[{{ $index }}][account_number]" value="{{ $method['account_number'] ?? '' }}" maxlength="60" inputmode="numeric" @disabled($type !== 'bank')></div>
        <div class="coin-field"><label>@lang('Account name')</label><input name="payment_methods[{{ $index }}][account_name]" value="{{ $method['account_name'] ?? '' }}" maxlength="160" @disabled($type !== 'bank')></div>
    </div>
    <div class="coin-pm-fields" data-fields="mobile_money">
        <div class="coin-field"><label>@lang('Provider')</label><input name="payment_methods[{{ $index }}][provider]" value="{{ $method['provider'] ?? '' }}" placeholder="@lang('e.g. Opay')" maxlength="120" @disabled($type !== 'mobile_money')></div>
        <div class="coin-field"><label>@lang('Phone number')</label><input name="payment_methods[{{ $index }}][phone_number]" value="{{ $method['phone_number'] ?? '' }}" maxlength="60" inputmode="tel" @disabled($type !== 'mobile_money')></div>
        <div class="coin-field"><label>@lang('Account name')</label><input name="payment_methods[{{ $index }}][account_name]" value="{{ $method['account_name'] ?? '' }}" maxlength="160" @disabled($type !== 'mobile_money')></div>
    </div>
    <div class="coin-pm-fields" data-fields="crypto">
        <div class="coin-field"><label>@lang('Network')</label><input name="payment_methods[{{ $index }}][network]" value="{{ $method['network'] ?? '' }}" placeholder="TRC20, ERC20, BEP20" maxlength="60" @disabled($type !== 'crypto')></div>
        <div class="coin-field coin-field--grow"><label>@lang('Wallet address')</label><input name="payment_methods[{{ $index }}][wallet_address]" value="{{ $method['wallet_address'] ?? '' }}" maxlength="200" @disabled($type !== 'crypto')></div>
        <div class="coin-field"><label>@lang('Memo / tag') <small>@lang('Optional')</small></label><input name="payment_methods[{{ $index }}][memo]" value="{{ $method['memo'] ?? '' }}" maxlength="120" @disabled($type !== 'crypto')></div>
    </div>
    <div class="coin-pm-fields" data-fields="other">
        <div class="coin-field coin-field--grow"><label>@lang('Payment details')</label><textarea name="payment_methods[{{ $index }}][details]" rows="2" maxlength="1000" @disabled($type !== 'other')>{{ $method['details'] ?? '' }}</textarea></div>
    </div>
    <div class="coin-field coin-pm-instructions">
        <label>@lang('Additional payment instructions') <small>@lang('Optional')</small></label>
        <textarea name="payment_methods[{{ $index }}][details]" rows="2" maxlength="1000" placeholder="@lang('Optional notes to help the buyer complete payment')" @disabled($type === 'other')>{{ $type === 'other' ? '' : ($method['details'] ?? '') }}</textarea>
    </div>
</div>
