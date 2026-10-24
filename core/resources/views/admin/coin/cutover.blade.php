@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <div class="alert alert--warning">
                This converts every user's real-currency balance to coins (or resets test
                balances to a flat amount), settles in-progress games, force-closes open table-game
                tables, and cancels any pending deposit/withdrawal. Always run a dry-run preview
                first. There is no undo.
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">1. Dry-run preview</h4>
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
                            <button type="submit" class="btn btn-outline--primary">Preview (no changes)</button>
                        </div>
                    </form>

                    @if(session('preview'))
                        @php($p = session('preview'))
                        <table class="table">
                            <tbody>
                                <tr><th>Users affected</th><td>{{ $p['users_affected'] }}</td></tr>
                                <tr><th>Total real balance</th><td>{{ $p['total_real_balance'] }}</td></tr>
                                <tr><th>Total coins to mint</th><td>{{ $p['total_coins_to_mint'] }}</td></tr>
                                <tr><th>Pricing errors</th><td>{{ count($p['pricing_errors']) }}</td></tr>
                                <tr><th>Running games to refund</th><td>{{ $p['running_games_to_refund'] }}</td></tr>
                                <tr><th>Open tables to force-close</th><td>{{ $p['open_tables_to_force_close'] }}</td></tr>
                                <tr><th>Pending deposits to cancel</th><td>{{ $p['pending_deposits_to_cancel'] }}</td></tr>
                                <tr><th>Pending withdrawals to cancel</th><td>{{ $p['pending_withdrawals_to_cancel'] }}</td></tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">2. Execute</h4>
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
                            <button type="submit" class="btn btn--danger">Run cutover</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table--light mb-0">
                            <thead><tr><th>#</th><th>Mode</th><th>Admin</th><th>Started</th><th>Finished</th><th>Summary</th></tr></thead>
                            <tbody>
                                @forelse($logs as $log)
                                    <tr>
                                        <td>{{ $log->id }}</td>
                                        <td>{{ $log->mode }}</td>
                                        <td>{{ $log->admin_id }}</td>
                                        <td>{{ $log->started_at }}</td>
                                        <td>{{ $log->finished_at }}</td>
                                        <td><pre class="mb-0 small">{{ json_encode($log->summary, JSON_PRETTY_PRINT) }}</pre></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted">No cutover has been run yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            {{ $logs->links() }}
        </div>
    </div>
@endsection
