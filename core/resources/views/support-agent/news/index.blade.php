<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>News · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <div class="support-topbar__right">
            <a href="{{ route('support-agent.news.create') }}" style="color:#063b29;background:var(--agent-green);text-decoration:none;font-size:12px;font-weight:800;padding:7px 14px;border-radius:9px">+ New post</a>
            <a href="{{ route('support-agent.dashboard') }}" style="color:var(--agent-muted);text-decoration:none;font-size:12px;font-weight:800;padding:7px 11px;border:1px solid var(--agent-line);border-radius:9px">Back to inbox</a>
            <div class="support-agent-profile">@if($agent->photo)<img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="">@else<span>{{ strtoupper(substr($agent->name,0,1)) }}</span>@endif<strong>{{ $agent->name }}<small>{{ $agent->title ?: 'Support agent' }}</small></strong></div>
        </div>
    </header>
    <main style="max-width:960px;margin:0 auto;padding:28px 20px">
        @if(session('status'))
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(0,239,135,.3);border-radius:12px;background:rgba(0,239,135,.08);color:#b3f9da">{{ session('status') }}</div>
        @endif

        <h1 style="font-size:20px;margin-bottom:6px">Site news</h1>
        <p style="color:var(--agent-muted);font-size:13px;margin:0 0 20px">Everything published on the site's News page. You can edit or remove your own posts.</p>

        <div style="display:grid;gap:12px">
            @forelse($posts as $post)
                <div style="display:flex;align-items:center;gap:14px;padding:14px;border:1px solid var(--agent-line);border-radius:14px;background:var(--agent-panel);flex-wrap:wrap">
                    @if($post->image)
                        <img src="{{ $post->imageUrl() }}" alt="" style="width:76px;height:48px;border-radius:9px;object-fit:cover;flex:none">
                    @endif
                    <div style="flex:1;min-width:200px">
                        <strong>{{ $post->title }}</strong>
                        <div style="color:var(--agent-muted);font-size:12px">
                            {{ $post->posted_by_type === 'agent' ? ($post->posted_by_name ?: 'Agent') : 'Admin' }}
                            · {{ $post->status ? 'Published' : 'Unpublished' }}
                            · {{ $post->views }} views
                            · {{ showDateTime($post->created_at) }}
                        </div>
                    </div>
                    @if($post->posted_by_type === 'agent' && $post->posted_by_id === $agent->id)
                        <a href="{{ route('support-agent.news.edit', $post->id) }}" style="padding:9px 14px;border:1px solid var(--agent-line);border-radius:10px;color:var(--agent-text);text-decoration:none;font-size:12px;font-weight:800">Edit</a>
                        <form method="post" action="{{ route('support-agent.news.destroy', $post->id) }}" onsubmit="return confirm('Delete this post?');">
                            @csrf
                            <button type="submit" style="padding:9px 14px;border:1px solid rgba(255,92,122,.3);border-radius:10px;background:rgba(255,92,122,.08);color:#ff9fb0;font-size:12px;font-weight:800;cursor:pointer">Delete</button>
                        </form>
                    @else
                        <span style="padding:9px 14px;border:1px dashed var(--agent-line);border-radius:10px;color:var(--agent-muted);font-size:12px;font-weight:700">Not yours</span>
                    @endif
                </div>
            @empty
                <div style="padding:40px;text-align:center;border:1px dashed var(--agent-line);border-radius:16px;color:var(--agent-muted)">No news posts yet. Be the first to publish one.</div>
            @endforelse
        </div>

        <div style="margin-top:18px">{{ $posts->links() }}</div>
    </main>
</body>
</html>
