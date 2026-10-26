@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <a href="{{ route('admin.coin.settings') }}" class="btn btn-sm btn-outline--primary"><i class="las la-arrow-left"></i> Coin Settings</a>
        </div>

        <div class="col-12">
            <div class="alert alert--warning d-flex align-items-center gap-2 mb-0">
                <i class="las la-flask fs-4"></i>
                <div>Test-kit data only. Visible because "Marketplace Test Kit" is enabled in Coin Settings — turn it off once you're done testing.</div>
            </div>
        </div>

        <div class="col-12">
            <div class="card border border--warning">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div><h5 class="mb-1"><i class="las la-shield-alt text-warning me-1"></i>Concurrency self-test</h5><p class="text-muted mb-0">MySQL/InnoDB only. Creates clearly labeled test accounts and a 100-coin listing, sends five concurrent claims, then reconciles the ledger. Test records remain for review.</p></div>
                    <form method="post" action="{{ route('admin.coin.test-kit.concurrency-self-test') }}" class="mb-0">@csrf<button type="submit" class="btn btn--primary" onclick="return confirm('Create test-only accounts, coins, and a listing, then run five concurrent claims?')"><i class="las la-random"></i> Run concurrency self-test</button></form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="las la-user-plus me-2"></i>Create Test Accounts</h5></div>
                <div class="card-body d-flex flex-column gap-2">
                    <form method="post" action="{{ route('admin.coin.test-kit.create-agent') }}" class="d-flex gap-2">
                        @csrf
                        <select name="type" class="form-control"><option value="agent">Payment agent</option><option value="supervisor">Supervisor</option></select>
                        <button type="submit" class="btn btn-sm btn--primary"><i class="las la-plus"></i> Create</button>
                    </form>
                    <form method="post" action="{{ route('admin.coin.test-kit.create-user') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn--primary w-100"><i class="las la-user"></i> Create Test User</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="las la-chart-line me-2"></i>Demo Rates</h5></div>
                <div class="card-body d-flex align-items-center">
                    <form method="post" action="{{ route('admin.coin.test-kit.demo-rates') }}" class="w-100">
                        @csrf
                        <button type="submit" class="btn btn--primary w-100"><i class="las la-sync"></i> Compute Demo Rates for All Currencies</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="las la-hand-holding-usd me-2"></i>Grant Test Float (to an agent)</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.coin.test-kit.grant-float') }}" class="d-flex gap-2">
                        @csrf
                        <select name="support_agent_id" class="form-control">
                            @foreach($testAgents as $a)<option value="{{ $a->id }}">{{ $a->username }}</option>@endforeach
                        </select>
                        <input type="number" name="amount" class="form-control" placeholder="Amount" value="1000">
                        <button type="submit" class="btn btn-sm btn--primary"><i class="las la-gift"></i> Grant</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="las la-coins me-2"></i>Grant Test Coins (to a user)</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.coin.test-kit.grant-coins') }}" class="d-flex gap-2">
                        @csrf
                        <select name="user_id" class="form-control">
                            @foreach($testUsers as $u)<option value="{{ $u->id }}">{{ $u->username }}</option>@endforeach
                        </select>
                        <input type="number" name="amount" class="form-control" placeholder="Amount" value="500">
                        <button type="submit" class="btn btn-sm btn--primary"><i class="las la-gift"></i> Grant</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="las la-tag me-2"></i>Create a Sample Listing</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.coin.test-kit.sample-listing') }}" class="d-flex gap-2">
                        @csrf
                        <select name="user_id" class="form-control">
                            @foreach($testUsers as $u)<option value="{{ $u->id }}">{{ $u->username }}</option>@endforeach
                        </select>
                        <input type="number" name="amount" class="form-control" placeholder="Amount" value="100">
                        <button type="submit" class="btn btn-sm btn--primary"><i class="las la-plus"></i> List</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><h5 class="mb-0"><i class="las la-clock me-2"></i>Simulate a Payment-Window Timeout</h5></div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.coin.test-kit.simulate-timeout') }}" class="d-flex gap-2">
                        @csrf
                        <select name="listing_id" class="form-control">
                            @foreach($sampleListings->where('status', 'on_sale') as $l)<option value="{{ $l->id }}">Listing #{{ $l->id }} ({{ $l->amount }})</option>@endforeach
                        </select>
                        <select name="support_agent_id" class="form-control">
                            @foreach($testAgents as $a)<option value="{{ $a->id }}">{{ $a->username }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn--primary"><i class="las la-play"></i> Claim &amp; Expire</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="las la-users me-2"></i>Test Accounts</h5></div>
                <div class="card-body">
                    <p class="mb-2"><i class="las la-user-tie text-success"></i> <strong>Agents/Supervisors:</strong> {{ $testAgents->pluck('username')->implode(', ') ?: 'none yet' }}</p>
                    <p class="mb-0"><i class="las la-user text-primary"></i> <strong>Users:</strong> {{ $testUsers->pluck('username')->implode(', ') ?: 'none yet' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
