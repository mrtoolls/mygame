<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Agent Inbox · Winxpot Care</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Mulish:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/support-agent.css') }}?v={{ @filemtime(public_path('assets/global/css/support-agent.css')) }}">
</head>
<body class="support-agent-app"
    data-list-url="{{ route('support-agent.conversations') }}"
    data-api-base="{{ url('/support-agent/conversations') }}">
    <header class="support-topbar">
        <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
        <div class="support-topbar__right"><span class="support-online-pill"><i></i> On shift</span><button class="support-notify-toggle" type="button" title="Enable desktop alerts">Enable alerts</button><div class="support-agent-profile">@if($agent->photo)<img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="">@else<span>{{ strtoupper(substr($agent->name,0,1)) }}</span>@endif<strong>{{ $agent->name }}<small>{{ $agent->title ?: 'Support agent' }}</small></strong></div><form method="post" action="{{ route('support-agent.logout') }}">@csrf<button class="support-logout" type="submit" aria-label="Sign out"><i>↗</i></button></form></div>
    </header>
    <main class="support-workspace">
        <aside class="support-inbox">
            <div class="support-inbox__heading"><div><span class="support-agent-eyebrow">CUSTOMER CARE</span><h1>Inbox</h1></div><span class="support-inbox-count" aria-label="Open conversations">0</span></div>
            <div class="support-inbox-tabs"><button type="button" class="is-active" data-filter="all">All chats</button><button type="button" data-filter="unread">Unread</button></div>
            <div class="support-conversation-list"><div class="support-inbox-empty">Loading your inbox…</div></div>
            <div class="support-inbox-foot"><span class="support-online-indicator"></span> Live chats are updating automatically</div>
        </aside>
        <section class="support-chat-view">
            <div class="support-chat-placeholder"><span class="support-placeholder-icon">✦</span><h2>Your support desk</h2><p>Select a conversation to read the full ticket history and reply to the player.</p><div class="support-placeholder-tip"><i>✧</i> Helpful, clear replies make a great player experience.</div></div>
            <header class="support-thread-header" hidden><button class="support-mobile-back" type="button" aria-label="Back to inbox">←</button><span class="support-thread-avatar">W</span><div class="support-thread-person"><strong class="support-thread-name"></strong><span class="support-thread-email"></span></div><span class="support-thread-ticket"></span><button class="support-claim-button" type="button" hidden>Take this chat</button><button class="support-close-button" type="button" hidden>Close chat</button></header>
            <div class="support-thread-messages" hidden></div>
            <form class="support-reply-form" hidden><textarea name="message" rows="1" maxlength="2000" placeholder="Write a helpful reply…" required></textarea><span>Enter to send · Shift + Enter for a new line</span><button type="submit">Send reply <b>→</b></button></form>
            <div class="support-thread-closed" hidden>This ticket is closed.</div>
        </section>
    </main>
    <div class="support-agent-toast" role="status" aria-live="polite"></div>
    <script src="{{ asset('assets/global/js/support-agent.js') }}?v={{ @filemtime(public_path('assets/global/js/support-agent.js')) }}" defer></script>
</body>
</html>
