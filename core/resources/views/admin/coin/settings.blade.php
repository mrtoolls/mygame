@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        @if($general->allow_single_approver)
            <div class="col-12">
                <div class="alert alert--warning d-flex align-items-center gap-2 mb-0">
                    <i class="las la-exclamation-triangle fs-4"></i>
                    <div><strong>Single-approver mode is ON.</strong> Float grants and large penalties can be approved by the same admin who requested them. Testing only — turn it off before any real money is involved.</div>
                </div>
            </div>
        @endif

        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="javascript:void(0)" icon="las la-coins" title="Coin Mode" value="{{ $general->coin_mode ? 'ON' : 'OFF' }}" bg="{{ $general->coin_mode ? 'success' : 19 }}" outline="true" />
        </div>
        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="javascript:void(0)" icon="las la-scale-balanced" title="Reconciliation" value="{{ $reconciliation['ok'] ? 'OK' : 'MISMATCH' }}" bg="{{ $reconciliation['ok'] ? 'success' : 'danger' }}" outline="true" />
        </div>
        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="javascript:void(0)" icon="las la-chart-pie" title="Circulating Supply" value="{{ number_format($reconciliation['circulating'], 0) }}" bg="primary" outline="true" />
        </div>
        <div class="col-xxl-3 col-sm-6">
            <x-widget style="6" link="javascript:void(0)" icon="las la-vault" title="Platform Ledger" value="{{ number_format($reconciliation['platform_balance'], 0) }}" bg="19" outline="true" />
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="las la-sliders-h me-2"></i>Coin Economy Controls</h5></div>
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    @if(!$general->coin_mode)
                        <form method="post" action="{{ route('admin.coin.mode.toggle') }}" class="d-flex align-items-center gap-2 flex-wrap">
                            @csrf
                            <input type="hidden" name="enable" value="1">
                            <label class="form-check-label small d-flex align-items-center gap-1"><input type="checkbox" name="confirm" value="1" class="form-check-input"> I understand this turns on real coin trading</label>
                            <button type="submit" class="btn btn--primary btn-sm"><i class="las la-power-off"></i> Turn Coin Mode ON</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.coin.mode.toggle') }}" class="confirmationBtn" data-question="Turn coin mode off? Everything goes back to real-currency wallets.">
                            @csrf
                            <input type="hidden" name="enable" value="0">
                            <button type="submit" class="btn btn-outline--danger btn-sm"><i class="las la-power-off"></i> Turn Coin Mode OFF</button>
                        </form>
                    @endif
                    <span class="vr d-none d-md-inline"></span>
                    <form method="post" action="{{ route('admin.coin.single-approver.toggle') }}">
                        @csrf
                        <input type="hidden" name="enable" value="{{ $general->allow_single_approver ? 0 : 1 }}">
                        <button type="submit" class="btn btn-outline--secondary btn-sm"><i class="las la-user-check"></i> {{ $general->allow_single_approver ? 'Disable' : 'Enable' }} Single-Approver</button>
                    </form>
                    <form method="post" action="{{ route('admin.coin.recompute') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline--primary btn-sm"><i class="las la-sync"></i> Recompute All Rates</button>
                    </form>
                    <form method="post" action="{{ route('admin.coin.test-kit.toggle') }}">
                        @csrf
                        <input type="hidden" name="enable" value="{{ $general->coin_marketplace_test_kit_enabled ? 0 : 1 }}">
                        <button type="submit" class="btn btn-outline--secondary btn-sm"><i class="las la-flask"></i> {{ $general->coin_marketplace_test_kit_enabled ? 'Disable' : 'Enable' }} Test Kit</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.coin.float.index') }}" class="btn btn-sm btn-outline--primary"><i class="las la-hand-holding-usd"></i> Agent Float Grants</a>
                    <a href="{{ route('admin.coin.disputes.index') }}" class="btn btn-sm btn-outline--primary"><i class="las la-gavel"></i> Trade Disputes</a>
                    <a href="{{ route('admin.coin.cutover.index') }}" class="btn btn-sm btn-outline--primary"><i class="las la-exchange-alt"></i> Cutover Tool</a>
                    @if($general->coin_marketplace_test_kit_enabled)
                        <a href="{{ route('admin.coin.test-kit.index') }}" class="btn btn-sm btn-outline--primary"><i class="las la-flask"></i> Marketplace Test Kit</a>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="las la-money-bill-wave me-2"></i>Coin Pricing per Currency</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead><tr><th>Currency</th><th>Buy Margin %</th><th>Sell Margin %</th><th>Buy Price / Coin</th><th>Sell Price / Coin</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                @forelse($currencies as $currency)
                                    @php($rate = $rates->get($currency->id))
                                    <tr>
                                        <td><span class="fw-bold">{{ $currency->code }}</span></td>
                                        <td><input form="coin-margin-{{ $currency->id }}" type="number" step="0.01" name="buy_margin_percent" value="{{ $rate->buy_margin_percent ?? 3 }}" class="form-control form-control-sm" style="width:90px"></td>
                                        <td><input form="coin-margin-{{ $currency->id }}" type="number" step="0.01" name="sell_margin_percent" value="{{ $rate->sell_margin_percent ?? 3 }}" class="form-control form-control-sm" style="width:90px"></td>
                                        <td>{{ $rate->buy_price ?? '—' }}</td>
                                        <td>{{ $rate->sell_price ?? '—' }}</td>
                                        <td>@if($rate?->flagged_for_review)<span class="badge badge--warning">Flagged</span>@else<span class="badge badge--success">OK</span>@endif</td>
                                        <td>
                                            <form id="coin-margin-{{ $currency->id }}" method="post" action="{{ route('admin.coin.margins.save', $currency->id) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn--primary"><i class="las la-save"></i> Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-muted py-4">No enabled currencies found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
