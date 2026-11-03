@php($type = $method['type'] ?? 'bank')
<div class="sa-method" data-type="{{ $type }}">
    <div class="sa-method__head">
        <select class="form-control sa-method__type" name="payment_methods[{{ $index }}][type]">
            <option value="bank" @selected($type === 'bank')>Bank transfer</option>
            <option value="mobile_money" @selected($type === 'mobile_money')>Mobile money</option>
            <option value="crypto" @selected($type === 'crypto')>Crypto</option>
            <option value="other" @selected($type === 'other')>Other</option>
        </select>
        <input class="form-control sa-method__label" name="payment_methods[{{ $index }}][label]" value="{{ $method['label'] ?? '' }}" placeholder="Label (optional), e.g. GTBank NGN" maxlength="120">
        <button type="button" class="sa-method__remove" aria-label="Remove this method"><i class="las la-trash"></i></button>
    </div>

    <div class="sa-method__fields" data-fields="bank">
        <div class="sa-field"><label>Bank name</label><input class="form-control" name="payment_methods[{{ $index }}][bank_name]" value="{{ $method['bank_name'] ?? '' }}" maxlength="160"></div>
        <div class="sa-field"><label>Account number</label><input class="form-control" name="payment_methods[{{ $index }}][account_number]" value="{{ $method['account_number'] ?? '' }}" maxlength="60"></div>
        <div class="sa-field"><label>Account name</label><input class="form-control" name="payment_methods[{{ $index }}][account_name]" value="{{ $method['account_name'] ?? '' }}" maxlength="160"></div>
    </div>
    <div class="sa-method__fields" data-fields="mobile_money">
        <div class="sa-field"><label>Provider</label><input class="form-control" name="payment_methods[{{ $index }}][provider]" value="{{ $method['provider'] ?? '' }}" placeholder="e.g. MTN MoMo" maxlength="120"></div>
        <div class="sa-field"><label>Phone number</label><input class="form-control" name="payment_methods[{{ $index }}][phone_number]" value="{{ $method['phone_number'] ?? '' }}" maxlength="60"></div>
        <div class="sa-field"><label>Account name</label><input class="form-control" name="payment_methods[{{ $index }}][account_name]" value="{{ $method['account_name'] ?? '' }}" maxlength="160"></div>
    </div>
    <div class="sa-method__fields" data-fields="crypto">
        <div class="sa-field"><label>Network</label><input class="form-control" name="payment_methods[{{ $index }}][network]" value="{{ $method['network'] ?? '' }}" placeholder="e.g. TRC20, ERC20, BEP20" maxlength="60"></div>
        <div class="sa-field sa-field--wide"><label>Wallet address</label><input class="form-control" name="payment_methods[{{ $index }}][wallet_address]" value="{{ $method['wallet_address'] ?? '' }}" maxlength="200"></div>
        <div class="sa-field"><label>Memo / tag (optional)</label><input class="form-control" name="payment_methods[{{ $index }}][memo]" value="{{ $method['memo'] ?? '' }}" maxlength="120"></div>
    </div>
    <div class="sa-method__fields" data-fields="other">
        <div class="sa-field sa-field--wide"><label>Details</label><textarea class="form-control" name="payment_methods[{{ $index }}][details]" rows="2" maxlength="500">{{ $method['details'] ?? '' }}</textarea></div>
    </div>
</div>
