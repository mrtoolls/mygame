<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Arena moderation · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <div class="support-topbar__right">
            <a href="{{ route('support-agent.dashboard') }}" style="color:var(--agent-muted);text-decoration:none;font-size:12px;font-weight:800;padding:7px 11px;border:1px solid var(--agent-line);border-radius:9px">Back to inbox</a>
            <div class="support-agent-profile">@if($agent->photo)<img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="">@else<span>{{ strtoupper(substr($agent->name,0,1)) }}</span>@endif<strong>{{ $agent->name }}<small>{{ $agent->title ?: 'Moderator' }}</small></strong></div>
        </div>
    </header>
    <main style="max-width:720px;margin:0 auto;padding:28px 20px">
        @if(session('status'))
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(0,239,135,.3);border-radius:12px;background:rgba(0,239,135,.08);color:#b3f9da">{{ session('status') }}</div>
        @endif

        <h1 style="font-size:20px;margin-bottom:16px">Arena posts awaiting approval</h1>

        <div style="display:grid;gap:14px">
            @forelse($posts as $post)
                <div style="padding:16px;border:1px solid var(--agent-line);border-radius:14px;background:var(--agent-panel)">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                        <span style="display:grid;place-items:center;width:34px;height:34px;border-radius:50%;background:rgba(0,239,135,.12);color:var(--agent-green);font-weight:800;font-size:13px">{{ strtoupper(substr($post->user->username ?? '?', 0, 1)) }}</span>
                        <div>
                            <strong>{{ $post->user->username ?? 'Deleted user' }}</strong>
                            <div style="color:var(--agent-muted);font-size:11.5px">{{ ucfirst($post->type) }} &middot; {{ showDateTime($post->created_at) }}</div>
                        </div>
                    </div>
                    @if($post->body)<p style="margin:0 0 10px;font-size:13.5px;line-height:1.6;white-space:pre-wrap">{{ $post->body }}</p>@endif
                    @if($post->image)<img src="{{ $post->imageUrl() }}" alt="" style="display:block;max-width:280px;max-height:200px;border-radius:12px;margin-bottom:10px;object-fit:cover">@endif
                    <div style="display:flex;gap:10px">
                        <form method="post" action="{{ route('support-agent.arena.approve', $post->id) }}">
                            @csrf
                            <button type="submit" style="padding:9px 14px;border:0;border-radius:10px;background:var(--agent-green);color:#063b29;font-size:12px;font-weight:800;cursor:pointer">Approve</button>
                        </form>
                        <button type="button" class="arena-reject-toggle" data-post-id="{{ $post->id }}" style="padding:9px 14px;border:1px solid rgba(255,92,122,.3);border-radius:10px;background:rgba(255,92,122,.08);color:#ff9fb0;font-size:12px;font-weight:800;cursor:pointer">Reject</button>
                    </div>
                    <form method="post" action="{{ route('support-agent.arena.reject', $post->id) }}" class="arena-reject-form" id="arena-reject-form-{{ $post->id }}" style="display:none;margin-top:10px;gap:8px" >
                        @csrf
                        <textarea name="message" maxlength="255" rows="2" required placeholder="Reason for rejection" style="width:100%;padding:9px 11px;border:1px solid var(--agent-line);border-radius:10px;background:var(--agent-bg,#0d1225);color:var(--agent-text);font:inherit;font-size:12.5px;margin-bottom:8px"></textarea>
                        <button type="submit" style="padding:9px 14px;border:0;border-radius:10px;background:#ff5c7a;color:#2a0712;font-size:12px;font-weight:800;cursor:pointer">Confirm rejection</button>
                    </form>
                </div>
            @empty
                <div style="padding:40px;text-align:center;border:1px dashed var(--agent-line);border-radius:16px;color:var(--agent-muted)">Nothing waiting for review right now.</div>
            @endforelse
        </div>

        <div style="margin-top:18px">{{ $posts->links() }}</div>
    </main>
    <script>
        document.querySelectorAll('.arena-reject-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var form = document.getElementById('arena-reject-form-' + btn.dataset.postId);
                form.style.display = form.style.display === 'none' ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
