@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <a href="{{ route('admin.coin.settings') }}" class="btn btn-sm btn-outline--primary"><i class="las la-arrow-left"></i> Coin Settings</a>
        </div>

        <div class="col-12">
            <div class="alert alert--warning d-flex align-items-start gap-2">
                <i class="las la-exclamation-triangle fs-4"></i>
                <div>This converts every user's real-currency balance to coins (or resets test balances to a flat amount), settles in-progress games, force-closes open table-game tables, and cancels any pending deposit/withdrawal. <strong>Always run a dry-run preview first. There is no undo.</strong></div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="las la-search me-2"></i>1. Dry-Run Preview</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.coin.cutover.preview') }}" class="row g-2 align-items-end mb-3">
                        @csrf
                        <div class="col-auto">
                            <select name="mode" class="form-control">
                                <option value="convert">Convert real balances to coins</option>
                                <option value="reset_test">Reset every account to a flat test amount</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <input type="number" name="reset_amount" class="form-control" placeholder="Reset amount (coins)">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-outline--primary"><i class="las la-search"></i> Preview (no changes)</button>
                        </div>
                    </form>

                    @if(session('preview'))
                        @php($p = session('preview'))
                        <div class="row g-3">
                            <div class="col-xxl-3 col-sm-6"><x-widget style="6" link="javascript:void(0)" icon="las la-users" title="Users Affected" value="{{ $p['users_affected'] }}" bg="primary" outline="true" /></div>
                            <div class="col-xxl-3 col-sm-6"><x-widget style="6" link="javascript:void(0)" icon="las la-money-bill" title="Real Balance Total" value="{{ number_format($p['total_real_balance'], 0) }}" bg="19" outline="true" /></div>
                            <div class="col-xxl-3 col-sm-6"><x-widget style="6" link="javascript:void(0)" icon="las la-coins" title="Coins to Mint" value="{{ number_format($p['total_coins_to_mint'], 0) }}" bg="success" outline="true" /></div>
                            <div class="col-xxl-3 col-sm-6"><x-widget style="6" link="javascript:void(0)" icon="las la-exclamation-circle" title="Pricing Errors" value="{{ count($p['pricing_errors']) }}" bg="{{ count($p['pricing_errors']) ? 'danger' : 'success' }}" outline="true" /></div>
                        </div>
                        <table class="table table--light mt-3 mb-0">
                            <tbody>
                                <tr><th><i class="las la-gamepad"></i> Running games to refund</th><td>{{ $p['running_games_to_refund'] }}</td></tr>
                                <tr><th><i class="las la-chess-board"></i> Open tables to force-close</th><td>{{ $p['open_tables_to_force_close'] }}</td></tr>
                                <tr><th><i class="las la-wallet"></i> Pending deposits to cancel</th><td>{{ $p['pending_deposits_to_cancel'] }}</td></tr>
                                <tr><th><i class="las la-hand-holding-usd"></i> Pending withdrawals to cancel</th><td>{{ $p['pending_withdrawals_to_cancel'] }}</td></tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header bg--danger bg-opacity-10"><h5 class="mb-0"><i class="las la-bolt me-2"></i>2. Execute (irreversible)</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.coin.cutover.execute') }}" class="row g-2 align-items-end confirmationBtn" data-question="This cannot be undone. Are you sure?">
                        @csrf
                        <div class="col-auto">
                            <select name="mode" class="form-control">
                                <option value="convert">Convert real balances to coins</option>
                                <option value="reset_test">Reset every account to a flat test amount</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <input type="number" name="reset_amount" class="form-control" placeholder="Reset amount (coins)">
                        </div>
                        <div class="col-auto form-check">
                            <input type="checkbox" name="confirm" value="1" class="form-check-input" id="cutoverConfirm">
                            <label class="form-check-label" for="cutoverConfirm">I have reviewed the preview and want to run this now</label>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn--danger"><i class="las la-bolt"></i> Run Cutover</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="las la-history me-2"></i>Cutover History</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead><tr><th>#</th><th>Mode</th><th>Admin</th><th>Started</th><th>Finished</th><th>Summary</th></tr></thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td><span class="badge badge--{{ $log->mode === 'convert' ? 'primary' : 'warning' }}">{{ $log->mode }}</span></td>
                                        <td>{{ $log->admin_id }}</td>
                                        <td>{{ $log->started_at }}</td>
                                        <td>{{ $log->finished_at }}</td>
                                        <td><pre class="mb-0 small">{{ json_encode($log->summary, JSON_PRETTY_PRINT) }}</pre></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No cutover has been run yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="mt-3">{{ $logs->links() }}</div>
        </div>
    </div>
@endsection
