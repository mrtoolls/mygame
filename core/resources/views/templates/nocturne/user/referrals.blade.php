@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Bonuses')</h1>

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
