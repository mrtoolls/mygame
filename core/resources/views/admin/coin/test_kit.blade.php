@extends('admin.layouts.app')

@section('panel')
    <div class="row g-4">
        <div class="col-12">
            <div class="alert alert--warning">Test-kit data only. Visible because "Marketplace test kit" is enabled in Coin Settings - turn it off once you're done testing.</div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>Create test accounts</h5>
                    <form method="post" action="{{ route('admin.coin.test-kit.create-agent') }}" class="d-flex gap-2 mb-2">
                        @csrf
                        <select name="type" class="form-control"><option value="agent">Payment agent</option><option value="supervisor">Supervisor</option></select>
                        <button type="submit" class="btn btn-sm btn--primary">Create</button>
                    </form>
                    <form method="post" action="{{ route('admin.coin.test-kit.create-user') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn--primary">Create test user</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>Demo rates</h5>
                    <form method="post" action="{{ route('admin.coin.test-kit.demo-rates') }}">
                        @csrf
                        <button type="submit" class="btn btn-sm btn--primary">Compute demo rates for all currencies</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>Grant test float (to an agent)</h5>
                    <form method="post" action="{{ route('admin.coin.test-kit.grant-float') }}" class="d-flex gap-2">
                        @csrf
                        <select name="support_agent_id" class="form-control">
                            @foreach($testAgents as $a)<option value="{{ $a->id }}">{{ $a->username }}</option>@endforeach
                        </select>
                        <input type="number" name="amount" class="form-control" placeholder="Amount" value="1000">
                        <button type="submit" class="btn btn-sm btn--primary">Grant</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>Grant test coins (to a user)</h5>
                    <form method="post" action="{{ route('admin.coin.test-kit.grant-coins') }}" class="d-flex gap-2">
                        @csrf
                        <select name="user_id" class="form-control">
                            @foreach($testUsers as $u)<option value="{{ $u->id }}">{{ $u->username }}</option>@endforeach
                        </select>
                        <input type="number" name="amount" class="form-control" placeholder="Amount" value="500">
                        <button type="submit" class="btn btn-sm btn--primary">Grant</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>Create a sample listing</h5>
                    <form method="post" action="{{ route('admin.coin.test-kit.sample-listing') }}" class="d-flex gap-2">
                        @csrf
                        <select name="user_id" class="form-control">
                            @foreach($testUsers as $u)<option value="{{ $u->id }}">{{ $u->username }}</option>@endforeach
                        </select>
                        <input type="number" name="amount" class="form-control" placeholder="Amount" value="100">
                        <button type="submit" class="btn btn-sm btn--primary">List</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5>Simulate a payment-window timeout</h5>
                    <form method="post" action="{{ route('admin.coin.test-kit.simulate-timeout') }}" class="d-flex gap-2">
                        @csrf
                        <select name="listing_id" class="form-control">
                            @foreach($sampleListings->where('status', 'on_sale') as $l)<option value="{{ $l->id }}">Listing #{{ $l->id }} ({{ $l->amount }})</option>@endforeach
                        </select>
                        <select name="support_agent_id" class="form-control">
                            @foreach($testAgents as $a)<option value="{{ $a->id }}">{{ $a->username }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn--primary">Claim then expire</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5>Test accounts</h5>
                    <p><strong>Agents/supervisors:</strong> {{ $testAgents->pluck('username')->implode(', ') ?: 'none yet' }}</p>
                    <p><strong>Users:</strong> {{ $testUsers->pluck('username')->implode(', ') ?: 'none yet' }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
