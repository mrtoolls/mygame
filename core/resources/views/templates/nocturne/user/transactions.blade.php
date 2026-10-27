@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-transactions-page">
    <h1 class="nocturne-page-title">@lang('Transactions')</h1>
    @if($coinMode ?? false)
        <p class="text-muted mb-3">@lang('Showing your coin wallet activity. Transactions made before coin mode was enabled are kept below for reference.')</p>
    @endif
    <div class="transection-section">
        <div class="show-filter text-end mb-3">
            <button class="btn btn--base showFilterBtn btn--sm" type="button"><i class="las la-filter"></i>
                @lang('Filter')</button>
        </div>
        <div class="card custom--card responsive-filter-card mb-4">
            <div class="card-body">
                <form>
                    <div class="d-flex flex-wrap gap-4">
                        <div class="flex-grow-1">
                            <label class="form--label">@lang('Transaction Number')</label>
                            <input class="form--control" name="search" type="text" value="{{ request()->search }}">
                        </div>
                        <div class="flex-grow-1">
                            <label class="form--label">@lang('Type')</label>
                            <select class="form--control select2" name="trx_type" data-minimum-results-for-search="-1">
                                <option value="">@lang('All')</option>
                                <option value="+" @selected(request()->trx_type == '+')>@lang('Plus')</option>
                                <option value="-" @selected(request()->trx_type == '-')>@lang('Minus')</option>
                            </select>
                        </div>
                        @unless($coinMode ?? false)
                            <div class="flex-grow-1">
                                <label class="form--label">@lang('Remark')</label>
                                <select class="form--control select2" name="remark" data-minimum-results-for-search="-1">
                                    <option value="">@lang('Any')</option>
                                    @foreach ($remarks as $remark)
                                        <option value="{{ $remark->remark }}" @selected(request()->remark == $remark->remark)>
                                            {{ __(keyToTitle($remark->remark)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endunless
                        <div class="flex-grow-1 align-self-end">
                            <button class="btn btn--base w-100"><i class="las la-filter"></i> @lang('Filter')</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="nocturne-activity-list">
            @forelse($transactions as $trx)
                <article class="nocturne-activity-card {{ $trx->trx_type == '+' ? 'is-credit' : 'is-debit' }}">
                    <div class="nocturne-activity-card__head">
                        <span class="nocturne-activity-card__icon"><i class="ph-fill {{ $trx->trx_type == '+' ? 'ph-arrow-down-left' : 'ph-arrow-up-right' }}"></i></span>
                        <div class="nocturne-activity-card__identity">
                            <span>{{ $trx->trx_type == '+' ? __('Money in') : __('Money out') }}</span>
                            <strong>{{ $trx->trx }}</strong>
                        </div>
                        <strong class="nocturne-activity-card__amount">
                            @if($coinMode ?? false){{ $trx->trx_type }} {{ $trx->amount_text }}@else{{ $trx->trx_type }} {{ showAmount($trx->amount) }}@endif
                        </strong>
                    </div>
                    <div class="nocturne-activity-card__details">
                        <div><span>@lang('Transacted')</span><strong>{{ showDateTime($trx->created_at) }} <small>{{ diffForHumans($trx->created_at) }}</small></strong></div>
                        <div><span>@lang('Post Balance')</span><strong>@if($coinMode ?? false){{ $trx->balance_text }}@else{{ showAmount($trx->post_balance) }}@endif</strong></div>
                    </div>
                    <p class="nocturne-activity-card__description">{{ ($coinMode ?? false) ? $trx->details : __($trx->details) }}</p>
                </article>
            @empty
                <div class="nocturne-wallet-empty"><i class="ph-fill ph-receipt"></i><strong>{{ __($emptyMessage) }}</strong></div>
            @endforelse
        </div>
        <div class="nocturne-activity-pagination">{{ paginateLinks($transactions) }}</div>
    </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .nocturne-transactions-page { min-width:0; }
        .nocturne-transactions-page .nocturne-page-title { margin:0 0 8px; font-size:clamp(22px,2vw,28px); font-weight:750; letter-spacing:-.035em; line-height:1.15; text-transform:none; }
        .nocturne-transactions-page>p { color:#9ca8c7!important; font-size:13px; line-height:1.6; }
        .nocturne-transactions-page .show-filter { margin:0 0 8px!important; }
        .nocturne-transactions-page .showFilterBtn { min-height:40px; padding:0 15px; border-radius:12px; font-size:12px; }
        .nocturne-transactions-page .responsive-filter-card { border:1px solid rgba(133,148,192,.17); border-radius:17px; background:linear-gradient(145deg,#171d35,#12182c); }
        .nocturne-transactions-page .responsive-filter-card .card-body { padding:clamp(14px,2vw,22px); }
        .nocturne-transactions-page .responsive-filter-card form>div { gap:12px!important; }
        .nocturne-transactions-page .form--label { margin-bottom:6px; color:#aeb9d5; font-size:11px; font-weight:650; }
        .nocturne-transactions-page .form--control { min-height:44px; border-radius:11px; font-size:13px; }
        .nocturne-transactions-page .responsive-filter-card .btn { min-height:44px; border-radius:11px; font-size:12px; }
        .nocturne-activity-list { display:grid; gap:11px; }
        .nocturne-activity-card { min-width:0; padding:16px 18px; border:1px solid rgba(133,148,192,.17); border-radius:17px; background:linear-gradient(145deg,#171d35,#12182c); box-shadow:0 10px 26px rgba(0,0,0,.12); }
        .nocturne-activity-card__head { display:flex; min-width:0; align-items:center; gap:12px; }
        .nocturne-activity-card__icon { display:grid; width:40px; height:40px; flex:0 0 40px; place-items:center; border-radius:13px; background:rgba(0,231,138,.1); color:#00e78a; font-size:19px; }
        .nocturne-activity-card.is-debit .nocturne-activity-card__icon { background:rgba(255,92,122,.1); color:#ff7189; }
        .nocturne-activity-card__identity { display:grid; min-width:0; gap:3px; }
        .nocturne-activity-card__identity>span,.nocturne-activity-card__details>div>span { color:#929dbb; font-size:10px; font-weight:750; letter-spacing:.06em; text-transform:uppercase; }
        .nocturne-activity-card__identity>strong { overflow:hidden; color:#eef1ff; font-size:12px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
        .nocturne-activity-card__amount { flex:0 0 auto; margin-inline-start:auto; color:#00e78a; font-size:15px; font-weight:750; text-align:end; overflow-wrap:anywhere; }
        .nocturne-activity-card.is-debit .nocturne-activity-card__amount { color:#ff7189; }
        .nocturne-activity-card__details { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:14px; padding-top:12px; border-top:1px solid rgba(133,148,192,.12); }
        .nocturne-activity-card__details>div { display:grid; min-width:0; gap:4px; }
        .nocturne-activity-card__details>div>strong { color:#d8deef; font-size:12px; font-weight:600; overflow-wrap:anywhere; }
        .nocturne-activity-card__details small { display:block; color:#818cab; font-size:10px; font-weight:500; }
        .nocturne-activity-card__description { margin:12px 0 0; color:#a4afcc; font-size:12px; line-height:1.55; overflow-wrap:anywhere; }
        .nocturne-activity-pagination { margin-top:16px; }
        .selection {
            display: block;
        }

        .select2-container--default .select2-selection--single {
            background-color: #0f1a24 !important;
            border: 1px solid rgba(255, 255, 255, 0.21) !important;
            border-radius: 4px;
            height: 50px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #fff;
            line-height: 36px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            top: 12px !important;
        }

        .select2 .dropdown-wrapper {
            display: none;
        }

        .select2-dropdown {
            background-color: #0f1a24;
        }

        .select2-results__option.select2-results__option--selected,
        .select2-results__option--selectable,
        .select2-container--default .select2-results__option--disabled {
            border-bottom: 0;
        }

        .select2-results__option.select2-results__option--selected,
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background-color: hsl(var(--base)) !important;
        }

        .select2-container:has(.select2-selection--single) {
            width: 100% !important;
        }
        @media(max-width:767.98px) {
            .nocturne-transactions-page .nocturne-page-title { font-size:22px; }
            .nocturne-transactions-page>p { font-size:12px; }
            .nocturne-activity-card { padding:13px; border-radius:15px; }
        }
        @media(max-width:359.98px) {
            .nocturne-activity-card__head { gap:9px; }
            .nocturne-activity-card__icon { width:35px; height:35px; flex-basis:35px; }
            .nocturne-activity-card__amount { max-width:46%; font-size:13px; }
            .nocturne-activity-card__details { grid-template-columns:1fr; gap:9px; }
        }
    </style>
@endpush
