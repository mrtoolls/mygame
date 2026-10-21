@php($isCrypto = (bool) ($deposit->gateway->crypto ?? false))
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Deposit {{ $deposit->trx }} · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <div class="support-topbar__right">
            <a href="{{ route('support-agent.payments.queue') }}" style="color:var(--agent-muted);text-decoration:none;font-size:12px;font-weight:800;padding:7px 11px;border:1px solid var(--agent-line);border-radius:9px">← Back to queue</a>
        </div>
    </header>
    <main style="max-width:720px;margin:0 auto;padding:28px 20px">
        <div style="padding:22px;border:1px solid var(--agent-line);border-radius:18px;background:var(--agent-panel)">
            <div style="display:flex;justify-content:space-between;align-items:start;gap:12px;flex-wrap:wrap;margin-bottom:16px">
                <div>
                    <strong style="font-size:18px">{{ $deposit->user?->username }}</strong>
                    <div style="color:var(--agent-muted);font-size:12px">{{ $deposit->user?->email }}</div>
                </div>
                <span style="padding:5px 11px;border-radius:999px;background:rgba(255,190,65,.12);color:#ffca69;font-size:11px;font-weight:800">Pending</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;font-size:13px">
                <div><span style="color:var(--agent-muted)">Amount</span><br><strong>{{ showAmount($deposit->amount) }}</strong></div>
                <div><span style="color:var(--agent-muted)">Trx</span><br><strong>{{ $deposit->trx }}</strong></div>
                <div><span style="color:var(--agent-muted)">Submitted</span><br><strong>{{ showDateTime($deposit->created_at) }}</strong></div>
                @if($deposit->confirmation_deadline_at)
                    <div><span style="color:var(--agent-muted)">Confirm by</span><br><strong>{{ showDateTime($deposit->confirmation_deadline_at) }}</strong></div>
                @endif
            </div>

            @if($deposit->disputed_at)
                <div style="margin-bottom:16px;padding:12px 14px;border:1px solid rgba(255,80,105,.3);border-radius:12px;background:rgba(255,80,105,.08);color:#ff9aaa">
                    <strong>Disputed</strong> {{ showDateTime($deposit->disputed_at) }}
                    @if($deposit->dispute_reason)<div style="margin-top:4px">{{ $deposit->dispute_reason }}</div>@endif
                </div>
            @endif

            @if($isCrypto)
                <div style="padding:14px;border:1px solid var(--agent-line);border-radius:12px;background:rgba(7,11,27,.42);margin-bottom:16px">
                    <div style="margin-bottom:10px"><span style="color:var(--agent-muted);font-size:12px">Paid from address</span><br><code style="word-break:break-all">{{ $deposit->payer_wallet_address }}</code></div>
                    @if($deposit->payment_proof)
                        <a href="{{ route('admin.download.attachment', encrypt(getFilePath('verify') . '/' . $deposit->payment_proof)) }}" target="_blank" style="color:var(--agent-green);font-weight:800;text-decoration:none">View payment receipt →</a>
                    @endif
                </div>
            @elseif($deposit->detail)
                <div style="padding:14px;border:1px solid var(--agent-line);border-radius:12px;background:rgba(7,11,27,.42);margin-bottom:16px">
                    @foreach($deposit->detail as $val)
                        <div style="margin-bottom:8px">
                            <span style="color:var(--agent-muted);font-size:12px">{{ $val->name ?? '' }}</span><br>
                            @if(($val->type ?? null) === 'file')
                                @if(!empty($val->value))
                                    <a href="{{ route('admin.download.attachment', encrypt(getFilePath('verify') . '/' . $val->value)) }}" target="_blank" style="color:var(--agent-green);font-weight:800;text-decoration:none">View attachment →</a>
                                @else
                                    <em>No file</em>
                                @endif
                            @else
                                <strong>{{ is_array($val->value ?? null) ? implode(', ', $val->value) : ($val->value ?? '') }}</strong>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <form method="post" action="{{ route('support-agent.payments.confirm', $deposit->id) }}">
                    @csrf
                    <button type="submit" style="padding:11px 18px;border:0;border-radius:11px;background:var(--agent-green);color:#063b29;font-size:13px;font-weight:800;cursor:pointer">Confirm &amp; credit user</button>
                </form>
                <button type="button" id="show-reject" style="padding:11px 18px;border:1px solid rgba(255,80,105,.35);border-radius:11px;background:transparent;color:#ff9aaa;font-size:13px;font-weight:800;cursor:pointer">Reject</button>
            </div>

            <form method="post" action="{{ route('support-agent.payments.reject', $deposit->id) }}" id="reject-form" style="display:none;margin-top:14px">
                @csrf
                <textarea name="message" rows="3" required maxlength="255" placeholder="Reason for rejection" style="width:100%;padding:10px;border:1px solid var(--agent-line);border-radius:10px;background:#0d1122;color:var(--agent-text);font:inherit;box-sizing:border-box"></textarea>
                <button type="submit" style="margin-top:8px;padding:10px 16px;border:0;border-radius:10px;background:#ff5069;color:#fff;font-size:13px;font-weight:800;cursor:pointer">Submit rejection</button>
            </form>
        </div>
    </main>
    <script>
        document.getElementById('show-reject')?.addEventListener('click', () => {
            document.getElementById('reject-form').style.display = 'block';
        });
    </script>
</body>
</html>
