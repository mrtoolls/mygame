<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Contact methods · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app">
    @include('support-agent.partials.topbar', ['agent' => $agent, 'active' => 'payments'])
    <div style="max-width:620px;margin:12px auto 0;padding:0 20px"><a href="{{ route('support-agent.payments.queue') }}" class="support-nav-link"><i class="ph-bold ph-arrow-left"></i> <span>Back to queue</span></a></div>
    <main style="max-width:620px;margin:0 auto;padding:28px 20px">
        @if(session('status'))
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(0,239,135,.3);border-radius:12px;background:rgba(0,239,135,.08);color:#b3f9da">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div style="margin-bottom:18px;padding:12px 16px;border:1px solid rgba(255,95,125,.3);border-radius:12px;background:rgba(255,95,125,.08);color:#ff9bad">{{ $errors->first() }}</div>
        @endif

        <h1 style="font-size:19px;margin-bottom:6px">Contact methods</h1>
        <p style="color:var(--agent-muted);font-size:13px;margin-bottom:20px">Choose how users can reach you directly from a manual payment page, in addition to in-app chat. Turn a channel on or off any time - its value is kept even while off.</p>

        <form method="post" action="{{ route('support-agent.contact-settings.update') }}">
            @csrf
            @php($methods = $agent->contact_methods ?? [])

            @foreach([
                ['key' => 'whatsapp', 'icon' => 'ph-whatsapp-logo', 'label' => 'WhatsApp', 'placeholder' => 'e.g. +2348012345678'],
                ['key' => 'telegram', 'icon' => 'ph-telegram-logo', 'label' => 'Telegram', 'placeholder' => 'e.g. @yourusername'],
                ['key' => 'phone', 'icon' => 'ph-phone', 'label' => 'Phone (call)', 'placeholder' => 'e.g. +2348012345678'],
                ['key' => 'email', 'icon' => 'ph-envelope-simple', 'label' => 'Email', 'placeholder' => 'e.g. you@example.com'],
            ] as $channel)
                @php($enabled = old($channel['key'] . '_enabled', $methods[$channel['key']]['enabled'] ?? false))
                @php($value = old($channel['key'] . '_value', $methods[$channel['key']]['value'] ?? ''))
                <div class="agent-channel">
                    <span class="agent-channel__icon"><i class="ph-fill {{ $channel['icon'] }}"></i></span>
                    <div class="agent-channel__body">
                        <label for="{{ $channel['key'] }}-value">{{ $channel['label'] }}</label>
                        <input id="{{ $channel['key'] }}-value" type="text" name="{{ $channel['key'] }}_value" value="{{ $value }}" placeholder="{{ $channel['placeholder'] }}">
                    </div>
                    <label class="agent-toggle">
                        <input type="checkbox" name="{{ $channel['key'] }}_enabled" value="1" class="channel-toggle" @checked($enabled)>
                        <span class="agent-toggle__switch"></span>
                    </label>
                </div>
            @endforeach

            <button type="submit" style="margin-top:20px;display:flex;align-items:center;gap:9px;height:46px;padding:0 18px;border:0;border-radius:12px;background:var(--agent-green);color:#063b29;font-size:12.5px;font-weight:900">Save contact methods</button>
        </form>
    </main>
</body>
</html>
