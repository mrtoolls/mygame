@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        @if($general->allow_single_approver)
            <div class="col-12">
                <div class="alert alert--warning mb-0">
                    <strong>Single-approver mode is ON.</strong> Float grants and large penalties can be approved by the same admin who requested them. This is for testing only - turn it off before any real money is involved.
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h4 class="mb-1">Coin mode: {{ $general->coin_mode ? 'ON' : 'OFF' }}</h4>
                        <p class="text-muted mb-0">
                            Reconciliation: {{ $reconciliation['ok'] ? 'OK' : 'MISMATCH (' . $reconciliation['difference'] . ')' }}
                            &middot; circulating {{ $reconciliation['circulating'] }} coins &middot; platform {{ $reconciliation['platform_balance'] }}
                        </p>
                    </div>
                    <div class="d-flex gap-2">
                        @if(!$general->coin_mode)
                            <form method="post" action="{{ route('admin.coin.mode.toggle') }}" class="d-flex align-items-center gap-2">
                                @csrf
                                <input type="hidden" name="enable" value="1">
                                <label class="form-check-label small"><input type="checkbox" name="confirm" value="1" class="form-check-input"> I understand this turns on real coin trading</label>
                                <button type="submit" class="btn btn--primary btn-sm">Turn coin mode ON</button>
                            </form>
                        @else
                            <form method="post" action="{{ route('admin.coin.mode.toggle') }}" class="confirmationBtn" data-question="Turn coin mode off? Everything goes back to real-currency wallets.">
                                @csrf
                                <input type="hidden" name="enable" value="0">
                                <button type="submit" class="btn btn-outline--danger btn-sm">Turn coin mode OFF</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('admin.coin.single-approver.toggle') }}">
                            @csrf
                            <input type="hidden" name="enable" value="{{ $general->allow_single_approver ? 0 : 1 }}">
                            <button type="submit" class="btn btn-outline--secondary btn-sm">{{ $general->allow_single_approver ? 'Disable' : 'Enable' }} single-approver (testing)</button>
                        </form>
                        <form method="post" action="{{ route('admin.coin.recompute') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline--primary btn-sm">Recompute all rates</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead><tr><th>Currency</th><th>Buy margin %</th><th>Sell margin %</th><th>Buy price / coin</th><th>Sell price / coin</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @foreach($currencies as $currency)
                                    @php($rate = $rates->get($currency->id))
                                    <tr>
                                        <td>{{ $currency->code }}</td>
                                        <td><input form="coin-margin-{{ $currency->id }}" type="number" step="0.01" name="buy_margin_percent" value="{{ $rate->buy_margin_percent ?? 3 }}" class="form-control form-control-sm" style="width:90px"></td>
                                        <td><input form="coin-margin-{{ $currency->id }}" type="number" step="0.01" name="sell_margin_percent" value="{{ $rate->sell_margin_percent ?? 3 }}" class="form-control form-control-sm" style="width:90px"></td>
                                        <td>{{ $rate->buy_price ?? '-' }}</td>
                                        <td>{{ $rate->sell_price ?? '-' }}</td>
                                        <td>@if($rate?->flagged_for_review)<span class="badge badge--warning">Flagged - large swing, not auto-applied</span>@else<span class="badge badge--success">OK</span>@endif</td>
                                        <td>
                                            <form id="coin-margin-{{ $currency->id }}" method="post" action="{{ route('admin.coin.margins.save', $currency->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn--primary">Save &amp; recompute</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
