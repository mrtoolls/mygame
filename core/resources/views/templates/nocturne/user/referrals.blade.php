@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Bonuses')</h1>

    <div class="nocturne-activate-card">
        <div class="nocturne-activate-card__body">
            <h4><i class="ph-bold ph-gift"></i> @lang('Activate Code for Bonus')</h4>
            <p>@lang('Enter the promo code and receive an exclusive reward from us and our partners. Offer is limited.')</p>
            <form action="{{ route('user.bonus.code.activate') }}" method="POST" class="disableSubmission nocturne-activate-card__form">
                @csrf
                <input type="text" name="code" placeholder="@lang('Enter Bonus Code')" required>
                <button type="submit" class="btn btn--base"><i class="ph-bold ph-gift"></i> @lang('Activate')</button>
            </form>
        </div>
    </div>

    <div class="nocturne-rakeback-card">
        <div class="nocturne-rakeback-card__head">
            <h4>@lang('Rakeback')</h4>
        </div>
        <div class="nocturne-rakeback-card__stats">
            <div>
                <span>@lang('Bets placed in 24 hours')</span>
                <strong>{{ showAmount($wagered24h) }}</strong>
            </div>
            <div>
                <span>@lang('Your rakeback percent')</span>
                <strong>{{ $currentTier ? showAmount($currentTier->rakeback_percent, currencyFormat: false) . '%' : __('None yet') }}</strong>
            </div>
        </div>
        @if ($rakebackTiers->count())
            <div class="nocturne-rakeback-scale">
                @foreach ($rakebackTiers as $tier)
                    <div class="nocturne-rakeback-scale__point {{ $currentTier?->id === $tier->id ? 'is-current' : '' }}">
                        <span class="nocturne-rakeback-scale__dot"></span>
                        <small>{{ showAmount($tier->rakeback_percent, currencyFormat: false) }}%</small>
                    </div>
                @endforeach
            </div>
        @endif
        <div class="nocturne-rakeback-card__claim">
            <div>
                <span>@lang('Available to receive')</span>
                <strong>{{ showAmount($claimable) }}</strong>
            </div>
            <form action="{{ route('user.vip.claim.rakeback') }}" method="POST" class="disableSubmission">
                @csrf
                <button type="submit" class="btn btn--base" {{ $claimable > 0 ? '' : 'disabled' }}><i class="ph-bold ph-coins"></i> @lang('Claim')</button>
            </form>
        </div>
    </div>

    <h4 class="mb-3"><i class="ph-bold ph-gift"></i> @lang('Active Bonuses')</h4>
    @if ($activeBonuses->isEmpty())
        <div class="card custom--card mb-4">
            <div class="card-body text-center">
                <h5 class="mb-0">@lang('You do not have any active bonuses yet')</h5>
            </div>
        </div>
    @else
        <div class="card custom--card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table transection-table">
                        <thead>
                            <tr>
                                <th>@lang('Code')</th>
                                <th>@lang('Amount')</th>
                                <th>@lang('Date')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activeBonuses as $redemption)
                                <tr>
                                    <td>{{ $redemption->bonusCode?->code ?? '-' }}</td>
                                    <td class="text--success">{{ showAmount($redemption->amount) }}</td>
                                    <td>{{ showDateTime($redemption->created_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <h4 class="mb-3">@lang('Affiliate Program')</h4>
    <div class="card custom--card mb-4">
        <div class="card-body">
            @if (auth()->user()->referrer)
                <p class="mb-3">@lang('You are referred by') <strong>{{ auth()->user()->referrer->fullname }}</strong></p>
            @endif
            <label class="form--label">@lang('Your Personal Referral Link')</label>
            <div class="input-group">
                <input class="form-control form--control referralURL" type="text"
                    value="{{ route('home') }}?reference={{ auth()->user()->username }}" readonly>
                <button class="input-group-text copytext" id="copyBoard" type="button"><i
                        class="fas fa-copy"></i> @lang('Copy')</button>
            </div>
        </div>
    </div>

    <h4 class="mb-3">@lang('Referred Users')</h4>
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card custom--card">
                <div class="card-body">
                    @if ($user->allReferrals->count() > 0 && $maxLevel > 0)
                        <div class="treeview-container">
                            <ul class="treeview">
                                <li class="items-expanded"> {{ $user->fullname }} ( {{ $user->username }} )
                                    @include('Template::partials.under_tree', [
                                        'user' => $user,
                                        'layer' => 0,
                                        'isFirst' => true,
                                    ])
                                </li>
                            </ul>
                        </div>
                    @else
                        <div class="text-center">
                            <h5>{{ __($emptyMessage) }}</h5>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
@endsection

@push('style-lib')
    <link type="text/css" href="{{ asset('assets/global/css/jquery.treeView.css') }}" rel="stylesheet">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/jquery.treeView.js') }}"></script>
@endpush

@push('style')
    <style type="text/css">
        @media (max-width: 425px) {
            .input-group-text {
                height: 49px;
            }
        }

        .copied::after {
            background-color: #{{ gs('base_color') }}
        }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";
            $('.treeview').treeView();

            $('#copyBoard').on('click', function() {
                var copyText = document.getElementsByClassName("referralURL");
                copyText = copyText[0];
                copyText.select();
                copyText.setSelectionRange(0, 99999);
                /*For mobile devices*/
                document.execCommand("copy");
                copyText.blur();
                this.classList.add('copied');
                setTimeout(() => this.classList.remove('copied'), 1500);
            });
        })(jQuery);
    </script>
@endpush
