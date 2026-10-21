<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $post ? 'Edit post' : 'New post' }} · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <div class="support-topbar__right">
            <a href="{{ route('support-agent.news.index') }}" style="color:var(--agent-muted);text-decoration:none;font-size:12px;font-weight:800;padding:7px 11px;border:1px solid var(--agent-line);border-radius:9px">← Back to news</a>
            <div class="support-agent-profile">@if($agent->photo)<img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="">@else<span>{{ strtoupper(substr($agent->name,0,1)) }}</span>@endif<strong>{{ $agent->name }}<small>{{ $agent->title ?: 'Support agent' }}</small></strong></div>
        </div>
    </header>
    <main style="max-width:720px;margin:0 auto;padding:28px 20px">
        <h1 style="font-size:20px;margin-bottom:20px">{{ $post ? 'Edit post' : 'New post' }}</h1>

        <form method="post" action="{{ $post ? route('support-agent.news.update', $post->id) : route('support-agent.news.store') }}" enctype="multipart/form-data">
            @csrf
            <div style="display:grid;gap:16px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:var(--agent-muted);font-size:11.5px;font-weight:800;text-transform:uppercase">Title</label>
                    <input type="text" name="title" value="{{ old('title', $post->title ?? '') }}" required style="width:100%;padding:11px 13px;border:1px solid var(--agent-line);border-radius:10px;background:var(--agent-bg,#0d1225);color:var(--agent-text);font:inherit;font-size:13.5px">
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;color:var(--agent-muted);font-size:11.5px;font-weight:800;text-transform:uppercase">Badge label (optional)</label>
                    <input type="text" name="badge_label" value="{{ old('badge_label', $post->badge_label ?? '') }}" placeholder="e.g. New Partnership" style="width:100%;padding:11px 13px;border:1px solid var(--agent-line);border-radius:10px;background:var(--agent-bg,#0d1225);color:var(--agent-text);font:inherit;font-size:13.5px">
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;color:var(--agent-muted);font-size:11.5px;font-weight:800;text-transform:uppercase">Excerpt</label>
                    <textarea name="excerpt" rows="2" maxlength="500" placeholder="Short summary shown on the news list" style="width:100%;padding:11px 13px;border:1px solid var(--agent-line);border-radius:10px;background:var(--agent-bg,#0d1225);color:var(--agent-text);font:inherit;font-size:13.5px;resize:vertical">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;color:var(--agent-muted);font-size:11.5px;font-weight:800;text-transform:uppercase">Cover image {{ $post ? '(leave empty to keep current)' : '' }}</label>
                    @if($post?->image)
                        <div style="margin-bottom:8px"><img src="{{ $post->imageUrl() }}" alt="" style="width:160px;height:90px;border-radius:10px;object-fit:cover"></div>
                    @endif
                    <input type="file" name="image" accept=".png,.jpg,.jpeg,.webp" {{ $post ? '' : 'required' }} style="width:100%;color:var(--agent-text);font-size:13px">
                    <small style="color:var(--agent-muted);font-size:11.5px">Recommended 1200x600</small>
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;color:var(--agent-muted);font-size:11.5px;font-weight:800;text-transform:uppercase">Body</label>
                    <textarea name="body" rows="10" required style="width:100%;padding:11px 13px;border:1px solid var(--agent-line);border-radius:10px;background:var(--agent-bg,#0d1225);color:var(--agent-text);font:inherit;font-size:13.5px;resize:vertical">{{ old('body', $post->body ?? '') }}</textarea>
                </div>
                <button type="submit" style="padding:13px 18px;border:0;border-radius:12px;background:var(--agent-green);color:#063b29;font-size:14px;font-weight:800;cursor:pointer">{{ $post ? 'Save changes' : 'Publish post' }}</button>
            </div>
        </form>
    </main>
</body>
</html>
