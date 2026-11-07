@php($agent = $agent ?? auth('support_agent')->user())
<header class="support-topbar">
    <a class="support-agent-brand" href="{{ route('support-agent.dashboard') }}"><span class="support-agent-brand__mark">W</span><span>Winxpot <b>Care</b></span></a>
    <div class="support-topbar__right">
        @if($agent->is_chat_agent)
            <a href="{{ route('support-agent.dashboard') }}" class="support-nav-link @if(($active ?? null) === 'inbox') is-active @endif"><i class="ph-fill ph-chats-circle"></i> <span>Inbox</span></a>
            <a href="{{ route('support-agent.messages.index') }}" class="support-nav-link @if(($active ?? null) === 'messages') is-active @endif"><i class="ph-fill ph-paper-plane-right"></i> <span>Messages</span></a>
        @endif
        @if($agent->is_payment_agent)
            <a href="{{ route('support-agent.payments.queue') }}" class="support-nav-link @if(($active ?? null) === 'payments') is-active @endif"><i class="ph-fill ph-hand-coins"></i> <span>Payments</span></a>
            <a href="{{ route('support-agent.coin.marketplace') }}" class="support-nav-link @if(($active ?? null) === 'coin') is-active @endif"><i class="ph-fill ph-coins"></i> <span>Coin Marketplace</span></a>
        @endif
        @if($agent->is_marketplace_supervisor)
            <a href="{{ route('support-agent.coin.disputes.index') }}" class="support-nav-link @if(($active ?? null) === 'disputes') is-active @endif"><i class="ph-fill ph-gavel"></i> <span>Disputes</span></a>
        @endif
        @if($agent->is_moderator)
            <a href="{{ route('support-agent.arena.queue') }}" class="support-nav-link @if(($active ?? null) === 'arena') is-active @endif"><i class="ph-fill ph-shield-check"></i> <span>Arena moderation</span></a>
        @endif
        @if($agent->is_news_editor)
            <a href="{{ route('support-agent.news.index') }}" class="support-nav-link @if(($active ?? null) === 'news') is-active @endif"><i class="ph-fill ph-newspaper"></i> <span>News</span></a>
        @endif
        <span class="support-online-pill"><i></i> On shift</span>
        <button class="support-notify-toggle" type="button" title="Enable desktop alerts">Enable alerts</button>
        <div class="support-agent-profile">@if($agent->photo)<img src="{{ asset('assets/images/support-agents/' . $agent->photo) }}" alt="">@else<span>{{ strtoupper(substr($agent->name,0,1)) }}</span>@endif<strong>{{ $agent->name }}<small>{{ $agent->title ?: 'Support agent' }}</small></strong></div>
        <form method="post" action="{{ route('support-agent.logout') }}">@csrf<button class="support-logout" type="submit" aria-label="Sign out"><i>↗</i></button></form>
    </div>
</header>
