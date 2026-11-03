@extends('admin.layouts.app')

@section('panel')
<div class="cc-admin">
    <header class="cc-hero">
        <a href="{{ route('admin.coin.settings') }}" class="cc-back"><i class="las la-arrow-left"></i> Coin Settings</a>
        <span class="cc-kicker">Coin economy · one-time migration</span>
        <h1 class="cc-title">Coin Cutover</h1>
        <p class="cc-intro">Converts every user's real-currency balance to coins (or resets test balances to a flat amount), settles in-progress games, force-closes open table-game tables, and cancels any pending deposit or withdrawal.</p>
    </header>

    <div class="cc-warning"><i class="las la-exclamation-triangle"></i><div><strong>Always run a dry-run preview first.</strong> There is no undo once you execute.</div></div>

    <section class="cc-panel">
        <div class="cc-panel-head"><span class="cc-step">1</span><div><h2 class="cc-panel-title">Dry-run preview</h2><p class="cc-panel-copy">Computes exactly what execute would do, without changing any data.</p></div></div>

        <form method="post" action="{{ route('admin.coin.cutover.preview') }}" class="cc-form">
            @csrf
            <div class="cc-field"><label>Mode</label><select name="mode" class="form-control"><option value="convert">Convert real balances to coins</option><option value="reset_test">Reset every account to a flat test amount</option></select></div>
            <div class="cc-field"><label>Reset amount (coins)</label><input type="number" name="reset_amount" class="form-control" placeholder="Only used by reset mode"></div>
            <button type="submit" class="btn btn-outline--primary"><i class="las la-search"></i> Preview (no changes)</button>
        </form>

        @if(session('preview'))
            @php($p = session('preview'))
            <div class="cc-stats">
                <div class="cc-stat"><span>Users affected</span><strong>{{ number_format($p['users_affected']) }}</strong></div>
                <div class="cc-stat"><span>Real balance total</span><strong>{{ number_format($p['total_real_balance'], 0) }}</strong></div>
                <div class="cc-stat cc-stat--accent"><span>Coins to mint</span><strong>{{ number_format($p['total_coins_to_mint'], 0) }}</strong></div>
                <div class="cc-stat {{ count($p['pricing_errors']) ? 'cc-stat--danger' : '' }}"><span>Pricing errors</span><strong>{{ count($p['pricing_errors']) }}</strong></div>
            </div>
            <div class="cc-checklist">
                <div class="cc-check"><i class="las la-gamepad"></i><span>Running games to refund</span><b>{{ $p['running_games_to_refund'] }}</b></div>
                <div class="cc-check"><i class="las la-chess-board"></i><span>Open tables to force-close</span><b>{{ $p['open_tables_to_force_close'] }}</b></div>
                <div class="cc-check"><i class="las la-wallet"></i><span>Pending deposits to cancel</span><b>{{ $p['pending_deposits_to_cancel'] }}</b></div>
                <div class="cc-check"><i class="las la-hand-holding-usd"></i><span>Pending withdrawals to cancel</span><b>{{ $p['pending_withdrawals_to_cancel'] }}</b></div>
            </div>
            @if(count($p['pricing_errors']))
                <div class="cc-errors">
                    <h3><i class="las la-exclamation-circle"></i> Pricing errors ({{ count($p['pricing_errors']) }})</h3>
                    <p class="cc-panel-copy">These users were skipped from the coin total above - fix their currency pricing before executing, or they'll be skipped during execute too.</p>
                    <ul>
                        @foreach($p['pricing_errors'] as $userId => $message)
                            <li><a href="{{ route('admin.users.detail', $userId) }}">User #{{ $userId }}</a> — {{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endif
    </section>

    <section class="cc-panel cc-panel--danger">
        <div class="cc-panel-head"><span class="cc-step cc-step--danger">2</span><div><h2 class="cc-panel-title">Execute <small>(irreversible)</small></h2><p class="cc-panel-copy">Only run this after reviewing the preview above.</p></div></div>

        <form method="post" action="{{ route('admin.coin.cutover.execute') }}" class="cc-form confirmationBtn" data-question="This cannot be undone. Are you sure?">
            @csrf
            <div class="cc-field"><label>Mode</label><select name="mode" class="form-control"><option value="convert">Convert real balances to coins</option><option value="reset_test">Reset every account to a flat test amount</option></select></div>
            <div class="cc-field"><label>Reset amount (coins)</label><input type="number" name="reset_amount" class="form-control" placeholder="Only used by reset mode"></div>
            <label class="cc-confirm"><input type="checkbox" name="confirm" value="1"><span>I have reviewed the preview and want to run this now</span></label>
            <button type="submit" class="btn btn--danger"><i class="las la-bolt"></i> Run cutover</button>
        </form>
    </section>

    <section class="cc-panel">
        <div class="cc-panel-head"><div><h2 class="cc-panel-title">Cutover history</h2></div></div>

        @forelse($logs as $log)
            <div class="cc-log">
                <div class="cc-log__head">
                    <span class="badge badge--{{ $log->mode === 'convert' ? 'primary' : 'warning' }}">{{ $log->mode === 'convert' ? 'Convert' : 'Reset test' }}</span>
                    <span class="cc-log__meta">#{{ $log->id }} · {{ optional(\App\Models\Admin::find($log->admin_id))->username ?? 'Admin #' . $log->admin_id }} · started {{ $log->started_at }} · finished {{ $log->finished_at ?? '—' }}</span>
                </div>
                <pre class="cc-log__summary">{{ json_encode($log->summary, JSON_PRETTY_PRINT) }}</pre>
            </div>
        @empty
            <div class="cc-empty"><i class="las la-history"></i><p>No cutover has been run yet.</p></div>
        @endforelse
        <div class="cc-pagination">{{ $logs->links() }}</div>
    </section>
</div>

<style>
.cc-admin{--cc-bg:#101629;--cc-card:#151d33;--cc-card-2:#11192e;--cc-line:rgba(157,174,220,.16);--cc-muted:#8f9bbc;--cc-text:#f4f6ff;--cc-green:#00d987;--cc-danger:#ff718c;--cc-warn:#ffc454;color:var(--cc-text)}
.cc-admin *{box-sizing:border-box}
.cc-back{display:inline-flex;align-items:center;gap:6px;margin-bottom:12px;color:#b8c2df;font-size:12px;font-weight:700;text-decoration:none}
.cc-back:hover{color:var(--cc-green)}
.cc-hero{margin-bottom:16px;padding:22px 24px;border:1px solid var(--cc-line);border-radius:18px;background:linear-gradient(135deg,#18213a,#12192d)}
.cc-kicker{display:block;margin-bottom:6px;color:var(--cc-green);font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
.cc-title{margin:0;color:var(--cc-text);font-size:clamp(20px,2.4vw,27px);font-weight:700;letter-spacing:-.025em}
.cc-intro{max-width:760px;margin:8px 0 0;color:var(--cc-muted);font-size:13px;line-height:1.55}
.cc-warning{display:flex;align-items:flex-start;gap:12px;margin-bottom:18px;padding:14px 16px;border:1px solid rgba(255,196,84,.25);border-radius:14px;background:rgba(255,196,84,.07);color:#ffe0a3;font-size:13px;line-height:1.55}
.cc-warning i{margin-top:1px;color:var(--cc-warn);font-size:18px}
.cc-warning strong{color:#fff}
.cc-panel{min-width:0;margin-bottom:18px;padding:20px;border:1px solid var(--cc-line);border-radius:16px;background:linear-gradient(145deg,var(--cc-card),var(--cc-card-2));box-shadow:0 10px 28px rgba(0,0,0,.1)}
.cc-panel--danger{border-color:rgba(255,113,140,.28)}
.cc-panel-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px}
.cc-step{display:grid;flex:0 0 32px;place-items:center;width:32px;height:32px;border-radius:10px;background:rgba(0,217,135,.12);color:var(--cc-green);font-size:13px;font-weight:800}
.cc-step--danger{background:rgba(255,113,140,.14);color:var(--cc-danger)}
.cc-panel-title{margin:0;color:var(--cc-text);font-size:16px;font-weight:700;letter-spacing:-.01em}
.cc-panel-title small{color:var(--cc-danger);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.cc-panel-copy{margin:4px 0 0;color:var(--cc-muted);font-size:12px;line-height:1.5}
.cc-form{display:flex;flex-wrap:wrap;align-items:end;gap:12px;margin-bottom:4px}
.cc-field{display:flex;flex-direction:column;gap:6px;min-width:200px;flex:1 1 220px}
.cc-field label{color:#c6cde3;font-size:11px;font-weight:700}
.cc-field .form-control{min-height:42px;border:1px solid var(--cc-line);border-radius:10px;background:#0e1528;color:var(--cc-text)}
.cc-confirm{display:flex;align-items:center;gap:8px;margin:0;color:#c7cfe3;font-size:12px;font-weight:600}
.cc-confirm input{width:16px;height:16px;accent-color:var(--cc-green)}
.cc-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:18px}
.cc-stat{min-width:0;padding:14px;border:1px solid var(--cc-line);border-radius:14px;background:#0d1428}
.cc-stat span{display:block;color:var(--cc-muted);font-size:11px;font-weight:700}
.cc-stat strong{display:block;margin-top:6px;overflow-wrap:anywhere;color:var(--cc-text);font-size:19px;font-weight:700}
.cc-stat--accent strong{color:var(--cc-green)}
.cc-stat--danger strong{color:var(--cc-danger)}
.cc-checklist{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:12px}
.cc-check{display:flex;align-items:center;gap:10px;padding:11px 13px;border:1px solid var(--cc-line);border-radius:12px;background:rgba(255,255,255,.015)}
.cc-check i{flex:0 0 auto;color:var(--cc-green);font-size:15px}
.cc-check span{flex:1;min-width:0;color:var(--cc-muted);font-size:12px}
.cc-check b{flex:0 0 auto;color:var(--cc-text);font-size:14px;font-weight:700}
.cc-errors{margin-top:14px;padding:14px 16px;border:1px solid rgba(255,113,140,.25);border-radius:14px;background:rgba(255,113,140,.06)}
.cc-errors h3{display:flex;align-items:center;gap:7px;margin:0;color:#ffb4c2;font-size:13px;font-weight:700}
.cc-errors ul{margin:10px 0 0;padding:0 0 0 18px;color:#e9ccd2;font-size:12px;line-height:1.7}
.cc-errors a{color:var(--cc-green);font-weight:700;text-decoration:none}
.cc-errors a:hover{text-decoration:underline}
.cc-log{padding:14px 16px;border:1px solid var(--cc-line);border-radius:14px;background:#0d1428}
.cc-log+.cc-log{margin-top:10px}
.cc-log__head{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:8px}
.cc-log__meta{color:var(--cc-muted);font-size:11.5px}
.cc-log__summary{margin:0;padding:10px 12px;overflow:auto;border-radius:10px;background:#070b17;color:#b7c0e0;font-size:11.5px;line-height:1.6}
.cc-empty{display:grid;place-items:center;gap:8px;padding:34px 16px;color:var(--cc-muted);text-align:center}
.cc-empty i{font-size:26px;color:var(--cc-green)}
.cc-pagination{padding-top:12px}
@media(max-width:700px){.cc-hero{padding:16px}.cc-panel{padding:15px}.cc-form{flex-direction:column;align-items:stretch}.cc-field{min-width:0}}
</style>
@endsection
