@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <h1 class="nocturne-page-title">@lang('Coin Marketplace')</h1>

            <section class="transection-section">
                <div class="card mb-3">
                    <div class="card-body">
                        <h5>@lang('Your coin balance')</h5>
                        <p class="mb-0">@lang('Available'): <strong>{{ $wallet->available_balance }}</strong> &middot; @lang('Held in listings/trades'): <strong>{{ $wallet->held_balance }}</strong></p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h5>@lang('Sell my coins')</h5>
                        <form method="post" action="{{ route('user.coin.listings.store') }}" class="d-flex gap-2 align-items-end flex-wrap">
                            @csrf
                            <div>
                                <label class="form-label">@lang('Amount (whole coins)')</label>
                                <input type="number" step="1" min="1" name="amount" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn--gradient">@lang('List for sale')</button>
                        </form>
                    </div>
                </div>

                @if($myListings->count())
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5>@lang('Your open listings')</h5>
                            <table class="table">
                                <thead><tr><th>@lang('Amount')</th><th>@lang('Status')</th><th></th></tr></thead>
                                <tbody>
                                    @foreach($myListings as $listing)
                                        <tr>
                                            <td>{{ $listing->amount }}</td>
                                            <td>{{ $listing->status }}</td>
                                            <td>
                                                @if($listing->status === 'on_sale')
                                                    <form method="post" action="{{ route('user.coin.listings.cancel', $listing->id) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline--danger">@lang('Cancel')</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="card mb-3">
                    <div class="card-body">
                        <h5>@lang('Buy coins from an agent')</h5>
                        @if($agents->count())
                            <form method="post" action="{{ route('user.coin.buy') }}" class="d-flex gap-2 align-items-end flex-wrap">
                                @csrf
                                <div>
                                    <label class="form-label">@lang('Agent')</label>
                                    <select name="support_agent_id" class="form-control" required>
                                        @foreach($agents as $agent)
                                            <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">@lang('Amount (whole coins)')</label>
                                    <input type="number" step="1" min="1" name="amount" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn--gradient">@lang('Buy')</button>
                            </form>
                        @else
                            <p class="text-muted mb-0">@lang('No agents are currently selling coins.')</p>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h5>@lang('Your trades')</h5>
                        <table class="table">
                            <thead><tr><th>#</th><th>@lang('Amount')</th><th>@lang('Status')</th><th></th></tr></thead>
                            <tbody>
                                @forelse($myTrades as $trade)
                                    <tr>
                                        <td>{{ $trade->id }}</td>
                                        <td>{{ $trade->amount }}</td>
                                        <td>{{ $trade->status }}</td>
                                        <td><a href="{{ route('user.coin.trade', $trade->id) }}" class="btn btn-sm btn--primary">@lang('Open')</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">@lang('No trades yet.')</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        {{ $myTrades->links() }}
                    </div>
                </div>
            </section>
        </div>
    </div>
@endsection
