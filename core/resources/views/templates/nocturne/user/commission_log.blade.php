@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
    <h1 class="nocturne-page-title">@lang('Commission History')</h1>
    <section class="transection-section">
        <div class="latest-transection">
            <div class="transection-table-scroller">
                <table class="transection-table table table--responsive--xl">
                    <thead>
                        <tr>
                            <th>@lang('Commission From')</th>
                            <th>@lang('Commission Level')</th>
                            <th>@lang('Amount')</th>
                            <th>@lang('Title')</th>
                            <th>@lang('Transaction')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td>{{ __($log->userFrom->username) }}</td>
                                <td>{{ __($log->level) }}</td>
                                <td>{{ showAmount($log->amount) }}</td>
                                <td>{{ __($log->title) }}</td>
                                <td>{{ $log->trx }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="mt-4">
                    {{ paginateLinks($logs) }}
                </div>
            </div>
        </div>
    </section>
        </div>
    </div>
@endsection
