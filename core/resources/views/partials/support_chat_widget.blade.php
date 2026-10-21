@php
    $botName = gs('chatbot_name') ?: 'Winxpot Assistant';
    $botPhoto = gs('chatbot_photo') ? asset('assets/images/chatbot/' . gs('chatbot_photo')) : null;
@endphp
<div class="winx-support-widget"
    data-agents-url="{{ route('support-chat.agents') }}"
    data-active-url="{{ route('support-chat.active') }}"
    data-start-url="{{ route('support-chat.start') }}"
    data-messages-url="{{ url('/support-chat') }}"
    data-authenticated="{{ auth()->check() ? '1' : '0' }}"
    data-name="{{ auth()->user()->fullname ?? '' }}"
    data-email="{{ auth()->user()->email ?? '' }}"
    data-bot-name="{{ $botName }}"
    data-bot-photo="{{ $botPhoto }}">
    <button class="winx-support-launcher" type="button" aria-label="Open Winxpot support" aria-expanded="false">
        <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M10 23v-2a14 14 0 0 1 28 0v3"/><path d="M10 22H8a4 4 0 0 0-4 4v7a4 4 0 0 0 4 4h5V22h-3Zm28 0h2a4 4 0 0 1 4 4v7a4 4 0 0 1-4 4h-5V22h3Zm0 15c-1 4-5 6-11 6"/></svg>
        <span class="winx-support-unread" hidden>0</span>
    </button>

    <section class="winx-support-panel" role="dialog" aria-label="Winxpot live support" aria-modal="false" hidden>
        <header class="winx-support-header">
            <div class="winx-support-header__identity">
                <div class="winx-support-header__mark"><svg viewBox="0 0 48 48" aria-hidden="true"><path d="M10 23v-2a14 14 0 0 1 28 0v3M10 22H8a4 4 0 0 0-4 4v7a4 4 0 0 0 4 4h5V22h-3Zm28 0h2a4 4 0 0 1 4 4v7a4 4 0 0 1-4 4h-5V22h3Zm0 15c-1 4-5 6-11 6"/></svg></div>
                <div><strong>Winxpot Support</strong><span><i></i> Here to help</span></div>
            </div>
            <button class="winx-support-close" type="button" aria-label="Close support">&times;</button>
        </header>

        <div class="winx-support-discovery">
            <div class="winx-support-intro"><span class="winx-support-eyebrow">YOUR SUPPORT TEAM</span><h2>How can we help?</h2><p>Choose an agent or start with our support assistant.</p></div>
            <div class="winx-support-agent-list" aria-live="polite"><div class="winx-support-loading">Finding your support team…</div></div>
            <button class="winx-support-assistant" type="button"><span class="winx-support-assistant__icon">@if($botPhoto)<img src="{{ $botPhoto }}" alt="">@else✦@endif</span><span><strong>Ask {{ $botName }}</strong><small>Quick answers, any time</small></span><b>›</b></button>
            @guest
                <details class="winx-support-contact"><summary>Add your details for a follow-up <span>(optional)</span></summary><div><input name="visitor_name" type="text" maxlength="120" placeholder="Your name"><input name="visitor_email" type="email" maxlength="190" placeholder="Email for replies"></div></details>
            @endguest
            <div class="winx-support-privacy">Please never share your password, payment card number, or verification code in chat.</div>
        </div>

        <div class="winx-support-chat" hidden>
            <div class="winx-support-chat-status"><span class="winx-support-chat-agent">{{ $botName }}</span><span class="winx-support-ticket"></span></div>
            <div class="winx-support-messages" role="log" aria-live="polite" aria-relevant="additions"></div>
            <form class="winx-support-composer"><textarea name="message" rows="1" maxlength="2000" placeholder="Write a message…" aria-label="Write a message" required></textarea><button type="submit" aria-label="Send message"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 4 17 8-17 8 3-8-3-8Zm3 8h14"/></svg></button></form>
            <button class="winx-support-back" type="button">← Support options</button>
        </div>
        <footer class="winx-support-footer">Powered by Winxpot Care <span>•</span> Secure support</footer>
    </section>
</div>
<link rel="stylesheet" href="{{ asset('assets/global/css/support-chat.css') }}?v={{ @filemtime(public_path('assets/global/css/support-chat.css')) }}">
<script src="{{ asset('assets/global/js/support-chat.js') }}?v={{ @filemtime(public_path('assets/global/js/support-chat.js')) }}" defer></script>
<script>
document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-open-support]');
    if (!trigger) return;
    var launcher = document.querySelector('.winx-support-launcher');
    if (!launcher) return;
    event.preventDefault();
    launcher.click();
});
</script>
