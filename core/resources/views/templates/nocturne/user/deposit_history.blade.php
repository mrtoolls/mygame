@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-wallet-page nocturne-wallet-page--history">
    <div class="nocturne-txn-head">
        <div>
            <span class="nocturne-wallet-kicker">@lang('YOUR ACTIVITY')</span>
            <h1 class="nocturne-page-title">{{ gs('coin_mode') ? __('Buy Coins History') : __('Deposit History') }}</h1>
        </div>
        <form class="nocturne-txn-search" method="get">
            <i class="ph-bold ph-magnifying-glass"></i>
            <input type="text" name="search" value="{{ request()->search }}" placeholder="@lang('Search by transaction ID')" autocomplete="off">
            <button type="submit">@lang('Search')</button>
        </form>
    </div>

    @php
        $txnStatusMeta = fn ($status) => match ((int) $status) {
            Status::PAYMENT_PENDING => ['label' => 'Pending', 'icon' => 'ph-hourglass-medium', 'cls' => 'pending'],
            Status::PAYMENT_SUCCESS => ['label' => 'Approved', 'icon' => 'ph-check-circle', 'cls' => 'success'],
            Status::PAYMENT_REJECT => ['label' => 'Rejected', 'icon' => 'ph-x-circle', 'cls' => 'reject'],
            default => ['label' => 'Initiated', 'icon' => 'ph-circle-dashed', 'cls' => 'initiate'],
        };
    @endphp

    <div class="nocturne-txn-list">
        @forelse($deposits as $deposit)
            @php
                $meta = $txnStatusMeta($deposit->status);
                $isManual = $deposit->method_code >= 1000 && $deposit->method_code <= 5000;
                $rowDetails = [];
                if ($isManual) {
                    foreach (@$deposit->detail ?? [] as $key => $info) {
                        $rowDetails[] = $info;
                        if ($info->type == 'file') {
                            $rowDetails[$key]->value = route('user.download.attachment', encrypt(getFilePath('verify') . '/' . $info->value));
                        }
                    }
                }
            @endphp
            <div class="nocturne-txn-card">
                <div class="nocturne-txn-card__top">
                    <span class="nocturne-txn-card__icon"><i class="ph-fill ph-wallet"></i></span>
                    <div class="nocturne-txn-card__id">
                        <strong>{{ $deposit->method_code < 5000 ? __(@$deposit->gateway->name) : __('Google Pay') }}</strong>
                        <small>{{ $deposit->trx }}</small>
                    </div>
                    <div class="nocturne-txn-card__amount">
                        <strong>{{ $deposit->coin_mode_purchase ? coinAmountText($deposit->coin_amount) : showAmount($deposit->amount) }}</strong>
                        @if($deposit->coin_mode_purchase)
                            <small class="nocturne-txn-coin-rate">@lang('Rate'): {{ showAmount($deposit->coin_buy_price_snapshot, currencyFormat: false) }}/{{ __('coin') }}</small>
                        @endif
                        <span class="nocturne-txn-chip nocturne-txn-chip--{{ $meta['cls'] }}"><i class="ph-fill {{ $meta['icon'] }}"></i> {{ __($meta['label']) }}</span>
                    </div>
                </div>
                <div class="nocturne-txn-card__bottom">
                    <div class="nocturne-txn-card__stat"><span><i class="ph-bold ph-calendar"></i> @lang('Date')</span><strong>{{ showDateTime($deposit->created_at, 'M d, Y · h:i A') }}</strong></div>
                    <div class="nocturne-txn-card__stat"><span><i class="ph-bold ph-receipt"></i> @lang('Charge')</span><strong>{{ showAmount($deposit->charge) }}</strong></div>
                    <div class="nocturne-txn-card__stat"><span><i class="ph-bold ph-arrows-left-right"></i> @lang('Conversion')</span><strong>{{ showAmount($deposit->final_amount, currencyFormat: false) }} {{ __($deposit->method_currency) }}</strong></div>
                    <div class="nocturne-txn-card__stat"><span><i class="ph-bold ph-clock"></i> @lang('Time')</span><strong>{{ diffForHumans($deposit->created_at) }}</strong></div>
                    <div class="nocturne-txn-card__action">
                        @if ($isManual)
                            <button type="button" class="nocturne-txn-detail-btn" data-info="{{ json_encode($rowDetails) }}" @if ($deposit->status == Status::PAYMENT_REJECT) data-feedback="{{ $deposit->admin_feedback }}" @endif>
                                <i class="ph-bold ph-eye"></i> @lang('Details')
                            </button>
                        @else
                            <span class="nocturne-txn-auto"><i class="ph-fill ph-lightning"></i> @lang('Auto-processed')</span>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="nocturne-wallet-empty"><i class="ph-fill ph-wallet"></i><strong>{{ __($emptyMessage) }}</strong></div>
        @endforelse
    </div>

    <div class="nocturne-txn-pagination">{{ paginateLinks($deposits) }}</div>
        </div>
    </div>

    {{-- DETAILS MODAL --}}
    <div class="nocturne-txn-modal" id="txnDetailModal" hidden>
        <div class="nocturne-txn-modal__backdrop" data-close-txn-modal></div>
        <div class="nocturne-txn-modal__sheet">
            <div class="nocturne-txn-modal__head">
                <h3>@lang('Submission details')</h3>
                <button type="button" class="nocturne-txn-modal__close" data-close-txn-modal><i class="ph-bold ph-x"></i></button>
            </div>
            <div class="nocturne-txn-modal__body">
                <div class="nocturne-txn-modal__fields"></div>
                <div class="nocturne-txn-modal__feedback"></div>
            </div>
        </div>
    </div>
@endsection

@push('style')
<style>
:root{--wallet-accent:#00e78a;--wallet-bg:#10152a;--wallet-panel:#151a31;--wallet-raised:#1b2140;--wallet-muted:#858eaf;--wallet-line:rgba(133,142,175,.18);--wallet-text:#f5f6ff}
.nocturne-wallet-page{max-width:900px;margin-inline:auto}
.nocturne-wallet-kicker{display:block;margin-bottom:3px;color:var(--wallet-accent);font-size:9px;font-weight:850;letter-spacing:.14em}
.nocturne-wallet-page .nocturne-page-title{margin:0;font-size:23px;font-weight:750;letter-spacing:-.035em;line-height:1.2;text-transform:none}
.nocturne-wallet-empty{display:grid;justify-items:center;gap:8px;padding:40px 20px;border:1px dashed var(--wallet-line);border-radius:18px;color:var(--wallet-muted);text-align:center}
.nocturne-wallet-empty i{color:var(--wallet-accent);font-size:28px}

.nocturne-txn-head{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap}
.nocturne-txn-search{display:flex;align-items:center;gap:9px;min-width:min(300px,100%);max-width:390px;padding:7px 9px 7px 13px;border:1px solid var(--wallet-line);border-radius:14px;background:var(--wallet-panel)}
.nocturne-txn-search i{color:var(--wallet-muted);font-size:15px}
.nocturne-txn-search input{flex:1;min-width:0;border:0;background:transparent;color:var(--wallet-text);font:inherit;font-size:13.5px;outline:0}
.nocturne-txn-search input::placeholder{color:#606986}
.nocturne-txn-search button{flex:none;min-height:36px;padding:0 14px;border:0;border-radius:10px;background:var(--wallet-accent);color:#062a23;font-size:12px;font-weight:750;cursor:pointer}

.nocturne-txn-list{display:grid;gap:14px}
.nocturne-txn-card{padding:18px 20px;border:1px solid var(--wallet-line);border-radius:20px;background:linear-gradient(155deg,#151a31,#10152a 78%);box-shadow:0 14px 36px rgba(0,0,0,.16)}
.nocturne-txn-card__top{display:flex;align-items:center;gap:13px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px dashed var(--wallet-line)}
.nocturne-txn-card__icon{display:grid;place-items:center;flex:0 0 44px;width:44px;height:44px;border-radius:13px;background:rgba(0,231,138,.1);color:var(--wallet-accent);font-size:19px}
.nocturne-txn-card__id{flex:1;min-width:0;display:grid;gap:2px}
.nocturne-txn-card__id strong{overflow:hidden;color:var(--wallet-text);font-size:14.5px;text-overflow:ellipsis;white-space:nowrap}
.nocturne-txn-card__id small{color:var(--wallet-muted);font-size:11.5px}
.nocturne-txn-card__amount{display:grid;justify-items:end;gap:6px;flex:0 0 auto}
.nocturne-txn-card__amount>strong{color:var(--wallet-text);font-size:15px;font-weight:750;overflow-wrap:anywhere;text-align:end}
.nocturne-txn-coin-rate{color:var(--wallet-muted);font-size:10.5px}

.nocturne-txn-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;font-size:10.5px;font-weight:800;white-space:nowrap}
.nocturne-txn-chip--pending{background:rgba(255,181,71,.14);color:#ffb547}
.nocturne-txn-chip--success{background:rgba(0,231,138,.14);color:var(--wallet-accent)}
.nocturne-txn-chip--reject{background:rgba(255,92,122,.14);color:#ff5c7a}
.nocturne-txn-chip--initiate{background:rgba(133,142,175,.16);color:var(--wallet-muted)}

.nocturne-txn-card__bottom{display:flex;flex-wrap:wrap;align-items:center;gap:18px 26px}
.nocturne-txn-card__stat{display:grid;gap:4px}
.nocturne-txn-card__stat span{display:flex;align-items:center;gap:5px;color:var(--wallet-muted);font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em}
.nocturne-txn-card__stat span i{color:var(--wallet-accent);font-size:11px}
.nocturne-txn-card__stat strong{color:var(--wallet-text);font-size:12px;font-weight:600;white-space:normal;overflow-wrap:anywhere}
.nocturne-txn-card__action{margin-left:auto}
.nocturne-txn-detail-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 15px;border:1px solid var(--wallet-line);border-radius:11px;background:var(--wallet-bg);color:var(--wallet-text);font-size:12.5px;font-weight:750;cursor:pointer}
.nocturne-txn-detail-btn:hover{border-color:rgba(0,231,138,.4);color:var(--wallet-accent)}
.nocturne-txn-auto{display:inline-flex;align-items:center;gap:6px;padding:9px 13px;border-radius:11px;background:rgba(0,231,138,.08);color:var(--wallet-accent);font-size:12px;font-weight:700}

.nocturne-txn-pagination{margin-top:18px}

.nocturne-txn-modal{position:fixed;inset:0;z-index:1200;display:flex;align-items:flex-end;justify-content:center}
.nocturne-txn-modal__backdrop{position:absolute;inset:0;background:rgba(5,8,18,.72);backdrop-filter:blur(3px)}
.nocturne-txn-modal__sheet{position:relative;z-index:1;width:100%;max-width:460px;max-height:80vh;display:flex;flex-direction:column;margin:0 16px 16px;border:1px solid var(--wallet-line);border-radius:22px;background:linear-gradient(165deg,#171d36,#11152a 80%);box-shadow:0 30px 70px rgba(0,0,0,.5);animation:txn-modal-up .22s ease}
@keyframes txn-modal-up{from{transform:translateY(24px);opacity:0}to{transform:translateY(0);opacity:1}}
.nocturne-txn-modal__head{display:flex;align-items:center;gap:12px;padding:20px 22px 14px;border-bottom:1px solid var(--wallet-line)}
.nocturne-txn-modal__head h3{flex:1;margin:0;color:var(--wallet-text);font-size:16px;font-weight:800}
.nocturne-txn-modal__close{flex:0 0 auto;border:0;background:transparent;color:var(--wallet-muted);font-size:18px;cursor:pointer}
.nocturne-txn-modal__body{padding:18px 22px 22px;overflow-y:auto}
.nocturne-txn-modal__fields{display:grid;gap:9px}
.nocturne-txn-field{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:11px 13px;border-radius:12px;background:rgba(255,255,255,.03);flex-wrap:wrap}
.nocturne-txn-field span{color:var(--wallet-muted);font-size:12px;font-weight:700}
.nocturne-txn-field strong{color:var(--wallet-text);font-size:13px;font-weight:700;text-align:right;overflow-wrap:anywhere}
.nocturne-txn-field a{color:var(--wallet-accent);font-weight:750;text-decoration:none}
.nocturne-txn-modal__feedback{margin-top:14px;padding:13px 15px;border:1px solid rgba(255,92,122,.22);border-radius:13px;background:rgba(255,92,122,.07)}
.nocturne-txn-modal__feedback span{display:block;margin-bottom:4px;color:#ff5c7a;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.nocturne-txn-modal__feedback p{margin:0;color:var(--wallet-text);font-size:13px}

@media(max-width:575.98px){
    .nocturne-wallet-page{width:100%;max-width:none}
    .nocturne-wallet-page .nocturne-page-title{font-size:20px}
    .nocturne-txn-head{align-items:stretch;gap:12px;margin-bottom:14px}
    .nocturne-txn-search{width:100%;min-width:0;max-width:none}
    .nocturne-txn-search input{font-size:12px}
    .nocturne-txn-card{padding:13px;border-radius:16px}
    .nocturne-txn-card__top{display:grid;grid-template-columns:38px minmax(0,1fr);gap:9px 11px;align-items:center;margin-bottom:12px;padding-bottom:12px}
    .nocturne-txn-card__icon{width:38px;height:38px;flex-basis:38px;border-radius:12px;font-size:17px}
    .nocturne-txn-card__id{gap:3px}
    .nocturne-txn-card__id strong{font-size:13px}
    .nocturne-txn-card__id small{font-size:10px;overflow-wrap:anywhere}
    .nocturne-txn-card__amount{grid-column:1/-1;display:flex;min-width:0;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
    .nocturne-txn-card__amount>strong{max-width:100%;font-size:15px;text-align:start}
    .nocturne-txn-chip{padding:5px 9px;font-size:10px}
    .nocturne-txn-coin-rate{margin-inline-start:auto;text-align:end}
    .nocturne-txn-card__bottom{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
    .nocturne-txn-card__stat{min-width:0;gap:4px}
    .nocturne-txn-card__stat span{font-size:9px}
    .nocturne-txn-card__stat strong{font-size:11.5px;line-height:1.45}
    .nocturne-txn-card__action{margin:0;grid-column:1/-1}
    .nocturne-txn-detail-btn,.nocturne-txn-auto{width:100%;justify-content:center}
    .nocturne-txn-modal__sheet{max-width:none;max-height:85vh}
}
@media(max-width:359.98px){.nocturne-txn-card__bottom{grid-template-columns:1fr}.nocturne-txn-card__amount>strong{font-size:14px}}
</style>
@endpush

@push('script')
<script>
"use strict";
(function($) {
    $('.nocturne-txn-detail-btn').on('click', function() {
        var info = $(this).data('info');
        var feedback = $(this).data('feedback');
        var html = '';
        (info || []).forEach(function(el) {
            if (el.type === 'file') {
                if (el.value) html += `<div class="nocturne-txn-field"><span>${el.name}</span><a href="${el.value}" target="_blank" rel="noopener"><i class="ph-bold ph-paperclip"></i> {{ __('Open attachment') }}</a></div>`;
            } else {
                html += `<div class="nocturne-txn-field"><span>${el.name}</span><strong>${el.value}</strong></div>`;
            }
        });
        $('#txnDetailModal .nocturne-txn-modal__fields').html(html || '<div class="nocturne-txn-field"><span>{{ __("No additional details") }}</span></div>');
        $('#txnDetailModal .nocturne-txn-modal__feedback').toggle(!!feedback).html(feedback ? `<span>{{ __('Admin feedback') }}</span><p></p>` : '');
        if (feedback) $('#txnDetailModal .nocturne-txn-modal__feedback p').text(feedback);
        $('#txnDetailModal').prop('hidden', false);
        $('body').css('overflow', 'hidden');
    });
    $('[data-close-txn-modal]').on('click', function() {
        $('#txnDetailModal').prop('hidden', true);
        $('body').css('overflow', '');
    });
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') { $('#txnDetailModal').prop('hidden', true); $('body').css('overflow', ''); }
    });
})(jQuery);
</script>
@endpush
