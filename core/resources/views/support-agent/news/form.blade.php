<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $post ? 'Edit post' : 'New post' }} · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    @include('support-agent.partials.topbar', ['agent' => $agent, 'active' => 'news'])
    <div style="max-width:720px;margin:12px auto 0;padding:0 20px"><a href="{{ route('support-agent.news.index') }}" class="support-nav-link"><i class="ph-bold ph-arrow-left"></i> <span>Back to news</span></a></div>
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
