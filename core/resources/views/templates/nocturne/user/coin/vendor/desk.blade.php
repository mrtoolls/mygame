@extends('Template::layouts.master')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/global/css/coin-marketplace.css') }}?v={{ @filemtime('assets/global/css/coin-marketplace.css') }}">
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content coin-marketplace-content">
            <h1 class="nocturne-page-title">@lang('Vendor Desk')</h1>

            <div class="coin-page">
                <div class="coin-hero">
                    <div class="coin-hero__copy">
                        <span class="coin-hero__eyebrow"><i class="ph-fill ph-storefront"></i> @lang('Coin economy · Vendor account')</span>
                        <p style="margin:8px 0 0;color:var(--nocturne-muted);font-size:13.5px;line-height:1.55;max-width:46ch">@lang('Manage your coin availability, pricing, payment details and trades. Changes to your rate apply to new trades only.')</p>
                        <form method="post" action="{{ route('user.coin-vendor.selling') }}" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:16px">
                            @csrf
                            <button type="submit" class="coin-btn coin-btn--primary"><i class="ph-bold ph-storefront"></i> {{ $vendor->is_selling ? __('Pause new sales') : __('Start selling coins') }}</button>
                            <span style="color:var(--nocturne-muted);font-size:12.5px">{{ $vendor->is_selling ? __('Buyers can see your offer.') : __('Your offer is hidden from buyers.') }}</span>
                        </form>
                    </div>
                    <span class="coin-hero__icon"><i class="ph-fill ph-storefront"></i></span>
                </div>

                <div class="coin-stats">
                    <div class="coin-stat"><span>@lang('Available coins')</span><strong>{{ showAmount($wallet->available_balance,0) }}</strong></div>
                    <div class="coin-stat"><span>@lang('Trade escrow')</span><strong>{{ showAmount(bcsub((string) $wallet->held_balance,(string) $wallet->vendor_bond_balance,8),0) }}</strong></div>
                    <div class="coin-stat"><span>@lang('Security bond')</span><strong>{{ showAmount($wallet->vendor_bond_balance,0) }}</strong></div>
                    <div class="coin-stat"><span>@lang('Open trades')</span><strong>{{ $openTrades }}</strong></div>
                    <div class="coin-stat"><span>@lang('Completed trades')</span><strong>{{ $vendor->completed_trades }}</strong></div>
                    <div class="coin-stat"><span>@lang('Reliability')</span><strong>{{ number_format($vendor->reliability_score,0) }}%</strong></div>
                    <div class="coin-stat"><span>@lang('Avg. release')</span><strong>{{ $vendor->average_release_minutes }} @lang('min')</strong></div>
                    <div class="coin-stat"><span>@lang('Daily limit used')</span><strong>{{ showAmount($dailyUsed,0) }} / {{ showAmount($vendor->daily_limit,0) }}</strong></div>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Your sell rate')</h2>
                            <p>@lang('Allowed range'): {{ $band ? number_format($band['min'],4) : '—' }} – {{ $band ? number_format($band['max'],4) : '—' }} {{ $vendor->currency?->code }} @lang('per coin'). @lang('Platform sell reference is a hard floor.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-tag"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin-vendor.rate') }}" class="coin-form">
                        @csrf
                        <div class="coin-field coin-field--grow">
                            <label>@lang('Rate per coin') ({{ $vendor->currency?->code }})</label>
                            <input type="number" name="sell_rate" step="0.00000001" min="0.00000001" value="{{ old('sell_rate',$vendor->sell_rate) }}" required>
                        </div>
                        <button type="submit" class="coin-btn coin-btn--primary">@lang('Save rate')</button>
                    </form>
                    <p style="margin:12px 0 0;color:var(--nocturne-muted);font-size:12.5px">@lang('Per 100'): {{ $vendor->currency?->code }} {{ showAmount((float) $vendor->sell_rate * 100,2) }} · @lang('Per 1,000'): {{ $vendor->currency?->code }} {{ showAmount((float) $vendor->sell_rate * 1000,2) }}</p>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Contact methods')</h2>
                            <p>@lang('Let buyers reach you directly, in addition to in-app trade chat. Turn a channel on or off any time - its value is kept even while off.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-chats-circle"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin-vendor.contact-methods') }}" class="coin-contact-form">
                        @csrf
                        @php($contactMethods = $vendor->contact_methods ?? [])
                        @foreach([
                            ['key' => 'whatsapp', 'icon' => 'ph-whatsapp-logo', 'label' => 'WhatsApp', 'placeholder' => 'e.g. +2348012345678'],
                            ['key' => 'telegram', 'icon' => 'ph-telegram-logo', 'label' => 'Telegram', 'placeholder' => 'e.g. @yourusername'],
                            ['key' => 'phone', 'icon' => 'ph-phone', 'label' => 'Phone (call)', 'placeholder' => 'e.g. +2348012345678'],
                            ['key' => 'email', 'icon' => 'ph-envelope-simple', 'label' => 'Email', 'placeholder' => 'e.g. you@example.com'],
                        ] as $channel)
                            @php($enabled = old($channel['key'] . '_enabled', $contactMethods[$channel['key']]['enabled'] ?? false))
                            @php($value = old($channel['key'] . '_value', $contactMethods[$channel['key']]['value'] ?? ''))
                            <div class="coin-contact-channel">
                                <span class="coin-contact-channel__icon"><i class="ph-fill {{ $channel['icon'] }}"></i></span>
                                <div class="coin-field coin-field--grow">
                                    <label for="vendor-{{ $channel['key'] }}-value">{{ $channel['label'] }}</label>
                                    <input id="vendor-{{ $channel['key'] }}-value" type="text" name="{{ $channel['key'] }}_value" value="{{ $value }}" placeholder="{{ $channel['placeholder'] }}">
                                </div>
                                <label class="coin-contact-toggle">
                                    <input type="checkbox" name="{{ $channel['key'] }}_enabled" value="1" class="coin-contact-toggle__input" @checked($enabled)>
                                    <span class="coin-contact-toggle__switch"></span>
                                </label>
                            </div>
                        @endforeach
                        <button type="submit" class="coin-btn coin-btn--primary" style="margin-top:8px">@lang('Save contact methods')</button>
                    </form>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Payment methods')</h2>
                            <p>@lang('Details are encrypted and may be shared with your trade partner from the trade chat.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-credit-card"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin-vendor.payment-methods') }}" id="coin-payment-methods-form">
                        @csrf
                        @if($errors->has('payment_methods') || collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'payment_methods.')))
                            <div class="coin-pm-errors" role="alert">
                                <strong>@lang('Please complete each payment method')</strong>
                                <ul>@foreach($errors->get('payment_methods') as $error)<li>{{ $error }}</li>@endforeach
                                    @foreach($errors->getMessages() as $key => $messages)@if(str_starts_with($key, 'payment_methods.'))@foreach($messages as $error)<li>{{ $error }}</li>@endforeach @endif @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="coin-pm-list" id="coin-pm-list">
                        @foreach(old('payment_methods', $vendor->encrypted_payment_methods ?: [['type' => 'bank', 'label' => '', 'details' => '']]) as $index => $method)
                            @include('Template::user.coin.vendor.payment_method_row', ['index' => $index, 'method' => (array) $method])
                        @endforeach
                        </div>
                        <div class="coin-pm-actions">
                            <button type="button" class="coin-btn coin-btn--outline" id="coin-pm-add"><i class="ph-bold ph-plus"></i> @lang('Add payment method')</button>
                            <button type="submit" class="coin-btn coin-btn--primary">@lang('Save payment methods')</button>
                        </div>
                    </form>
                    <template id="coin-pm-template">@include('Template::user.coin.vendor.payment_method_row', ['index' => '__INDEX__', 'method' => []])</template>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Buy from member listings')</h2>
                            <p>@lang('These member listings use their locked coin sell reference. Your own listings are hidden.')</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-list-bullets"></i></span>
                    </div>
                    @forelse($listings as $listing)
                        <div class="coin-list">
                            <div class="coin-list__row">
                                <span class="coin-list__avatar"><i class="ph-fill ph-coin"></i></span>
                                <div class="coin-list__body">
                                    <span class="coin-list__title">{{ showAmount($listing->amount,0) }} @lang('coins')</span>
                                    <span class="coin-list__subtitle">@lang('Listing') #{{ $listing->id }} · {{ $listing->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="coin-list__trailing">
                                    <form method="post" action="{{ route('user.coin-vendor.listings.claim',$listing->id) }}">
                                        @csrf
                                        <button type="submit" class="coin-btn coin-btn--outline coin-btn--sm">@lang('Buy this listing')</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="coin-empty"><i class="ph-fill ph-coin"></i><p>@lang('No member listings are available right now.')</p></div>
                    @endforelse
                    <div class="mt-3">{{ $listings->links() }}</div>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div><h2>@lang('Your trades')</h2></div>
                        <span class="coin-section__icon"><i class="ph-fill ph-arrows-left-right"></i></span>
                    </div>
                    @forelse($trades as $trade)
                        <div class="coin-list">
                            <a href="{{ route('user.coin.trade',$trade->id) }}" class="coin-list__row">
                                <span class="coin-list__avatar"><i class="ph-fill ph-arrows-left-right"></i></span>
                                <div class="coin-list__body">
                                    <span class="coin-list__title">@lang('Trade') #{{ $trade->id }}</span>
                                    <span class="coin-list__subtitle">{{ $trade->coin_seller_id == (int) auth()->id() ? __('Selling') : __('Buying') }} · {{ $trade->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="coin-list__trailing">
                                    <span class="coin-list__amount">{{ showAmount($trade->amount,0) }}<small>@lang('coins')</small></span>
                                    <span class="coin-badge coin-badge--{{ $trade->status }}">{{ str_replace('_',' ',ucfirst($trade->status)) }}</span>
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="coin-empty"><i class="ph-fill ph-arrows-left-right"></i><p>@lang('No trades yet.')</p></div>
                    @endforelse
                    <div class="mt-3">{{ $trades->links() }}</div>
                </div>

                <div class="coin-section">
                    <div class="coin-section__head">
                        <div>
                            <h2>@lang('Close your vendor account')</h2>
                            <p>@lang('Resigning stops new offers. Open trades must still be resolved. Your bond stays reserved for :days days and requires admin approval to release.', ['days' => (int) (gs('coin_vendor_bond_cooldown_days') ?? 14)])</p>
                        </div>
                        <span class="coin-section__icon"><i class="ph-fill ph-x-circle"></i></span>
                    </div>
                    <form method="post" action="{{ route('user.coin-vendor.resign') }}" onsubmit="return confirm('{{ __('Stop taking new vendor trades and begin the bond cooldown?') }}')">
                        @csrf
                        <button type="submit" class="coin-btn coin-btn--danger">@lang('Resign as vendor')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <style>
        .coin-pm-list{display:grid;gap:12px}
        .coin-pm-row{min-width:0;padding:14px;border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:14px;background:rgba(8,12,27,.28)}
        .coin-pm-head{display:grid;grid-template-columns:minmax(150px,.7fr) minmax(180px,1.5fr) 40px;align-items:end;gap:10px}
        .coin-pm-fields{display:none;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding-top:14px;margin-top:12px;border-top:1px dashed var(--nocturne-line,rgba(150,165,210,.16))}
        .coin-pm-row[data-type="bank"] .coin-pm-fields[data-fields="bank"],.coin-pm-row[data-type="mobile_money"] .coin-pm-fields[data-fields="mobile_money"],.coin-pm-row[data-type="crypto"] .coin-pm-fields[data-fields="crypto"],.coin-pm-row[data-type="other"] .coin-pm-fields[data-fields="other"]{display:grid}
        .coin-pm-row .coin-field{min-width:0}
        .coin-pm-row .coin-field small{color:var(--nocturne-muted);font-size:.82em;font-weight:500}
        .coin-pm-row input:disabled,.coin-pm-row textarea:disabled{display:none}
        .coin-pm-instructions{margin-top:12px}
        .coin-pm-remove{display:grid;place-items:center;width:40px;height:42px;border:1px solid rgba(255,105,130,.25);border-radius:10px;background:rgba(255,105,130,.08);color:#ff7791;cursor:pointer}
        .coin-pm-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
        .coin-pm-errors{margin:0 0 12px;padding:12px 14px;border:1px solid rgba(255,105,130,.3);border-radius:12px;background:rgba(255,105,130,.08);color:#ffd8df;font-size:12px}
        .coin-pm-errors strong{display:block;margin-bottom:5px}.coin-pm-errors ul{margin:0;padding-left:18px}
        @media(max-width:700px){.coin-pm-head{grid-template-columns:minmax(0,1fr) 40px}.coin-pm-head>.coin-field:first-child{grid-column:1/-1}.coin-pm-fields{grid-template-columns:minmax(0,1fr)}.coin-pm-actions .coin-btn{flex:1 1 100%;justify-content:center}}
        .coin-contact-form{display:grid;gap:12px}
        .coin-contact-channel{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:14px;background:rgba(8,12,27,.28)}
        .coin-contact-channel__icon{display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;border-radius:11px;background:rgba(0,239,135,.1);color:var(--nocturne-green,#00ef87);font-size:18px}
        .coin-contact-channel .coin-field{margin:0}
        .coin-contact-toggle{position:relative;flex:0 0 auto;display:inline-block;width:42px;height:24px}
        .coin-contact-toggle__input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer}
        .coin-contact-toggle__switch{position:absolute;inset:0;border-radius:999px;background:rgba(150,165,210,.25);transition:background .15s ease}
        .coin-contact-toggle__switch::before{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;transition:transform .15s ease}
        .coin-contact-toggle__input:checked + .coin-contact-toggle__switch{background:var(--nocturne-green,#00ef87)}
        .coin-contact-toggle__input:checked + .coin-contact-toggle__switch::before{transform:translateX(18px)}
        @media(max-width:700px){.coin-contact-channel{flex-wrap:wrap}}
    </style>
    <script>
        (() => {
            const list = document.getElementById('coin-pm-list');
            const template = document.getElementById('coin-pm-template');
            const addButton = document.getElementById('coin-pm-add');
            if (!list || !template || !addButton) return;
            let nextIndex = Math.max(-1, ...Array.from(list.querySelectorAll('.coin-pm-type'), (select) => {
                const match = select.name.match(/\[(\d+)\]\[type\]$/);
                return match ? Number(match[1]) : -1;
            })) + 1;
            const syncRow = (row) => {
                const type = row.querySelector('.coin-pm-type')?.value || '';
                row.dataset.type = type;
                row.querySelectorAll('.coin-pm-fields').forEach((group) => {
                    const active = group.dataset.fields === type;
                    group.querySelectorAll('input,select,textarea').forEach((field) => { field.disabled = !active; });
                });
                const extra = row.querySelector('.coin-pm-instructions textarea');
                if (extra) extra.disabled = type === 'other';
            };
            const wireRow = (row) => {
                row.querySelector('.coin-pm-type')?.addEventListener('change', () => syncRow(row));
                row.querySelector('.coin-pm-remove')?.addEventListener('click', () => {
                    if (list.children.length > 1) row.remove();
                    else row.querySelectorAll('input,textarea').forEach((field) => { field.value = ''; });
                });
                syncRow(row);
            };
            list.querySelectorAll('.coin-pm-row').forEach(wireRow);
            addButton.addEventListener('click', () => {
                if (list.children.length >= 6) return;
                const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
                const holder = document.createElement('div');
                holder.innerHTML = html.trim();
                const row = holder.firstElementChild;
                list.appendChild(row);
                wireRow(row);
            });
        })();
    </script>
@endsection
