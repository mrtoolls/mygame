@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <div class="msg-page-heading">
                <div>
                    <span class="msg-eyebrow"><i class="ph-fill ph-chats-circle"></i> @lang('Your conversations')</span>
                    <h1 class="nocturne-page-title">@lang('Messages')</h1>
                    <p>@lang('A private place to talk with coin vendors and the Winxpot support team.')</p>
                </div>
                <button type="button" class="msg-new-trigger" id="msgNewOpen" @disabled($contacts->isEmpty())>
                    <i class="ph-bold ph-plus"></i><span>@lang('New message')</span>
                </button>
            </div>

            @if(session('notify'))
                <div class="msg-notice msg-notice--success">{{ session('notify')[0][1] ?? '' }}</div>
            @endif
            @if($errors->any())
                <div class="msg-notice msg-notice--error">{{ $errors->first() }}</div>
            @endif

            <section class="msg-workspace" id="msgWorkspace" data-mobile-view="{{ $activeData ? 'chat' : 'list' }}">
                <aside class="msg-sidebar">
                    <div class="msg-sidebar__head">
                        <div><span class="msg-sidebar__title">@lang('Inbox')</span><span class="msg-sidebar__count">{{ $conversations->count() }}</span></div>
                        <label class="msg-search"><i class="ph-bold ph-magnifying-glass"></i><input id="msgSearch" type="search" placeholder="@lang('Search conversations')" autocomplete="off"></label>
                    </div>
                    <div class="msg-conversation-list" id="msgConversationList">
                        @forelse($conversations as $conversation)
                            <a href="{{ route('user.messages.show', $conversation['id']) }}" class="msg-conversation {{ ($activeData['id'] ?? null) === $conversation['id'] ? 'is-active' : '' }}" data-search="{{ strtolower($conversation['name'] . ' ' . $conversation['role'] . ' ' . $conversation['preview']) }}">
                                <span class="msg-avatar">
                                    @if(!empty($conversation['avatar']))<img src="{{ $conversation['avatar'] }}" alt="">@else<i class="ph-fill {{ $conversation['role'] === 'Coin Vendor' ? 'ph-storefront' : 'ph-headset' }}"></i>@endif
                                    @if($conversation['unread'])<b class="msg-unread-dot"></b>@endif
                                </span>
                                <span class="msg-conversation__copy">
                                    <span class="msg-conversation__top"><strong>{{ $conversation['name'] }}</strong><time>{{ $conversation['time'] }}</time></span>
                                    <span class="msg-conversation__role">{{ __($conversation['role']) }}</span>
                                    <span class="msg-conversation__preview">{{ $conversation['preview'] ?: __('Start a conversation') }}</span>
                                </span>
                                @if($conversation['unread'])<span class="msg-unread-count">{{ $conversation['unread'] > 99 ? '99+' : $conversation['unread'] }}</span>@endif
                            </a>
                        @empty
                            <div class="msg-list-empty"><span><i class="ph-fill ph-chat-circle-dots"></i></span><strong>@lang('Your inbox is ready')</strong><p>@lang('Start a conversation with a vendor or our support team.')</p></div>
                        @endforelse
                    </div>
                    <div class="msg-sidebar__foot"><i class="ph-fill ph-lock-key"></i> @lang('Private, one-to-one conversations')</div>
                </aside>

                <main class="msg-chat" id="msgChatPanel">
                    @if($activeData)
                        <header class="msg-chat__header">
                            <button type="button" class="msg-back-mobile" id="msgBackToList" aria-label="@lang('Back to inbox')"><i class="ph-bold ph-arrow-left"></i></button>
                            <span class="msg-avatar msg-avatar--large">
                                @if(!empty($activeData['avatar']))<img src="{{ $activeData['avatar'] }}" alt="">@else<i class="ph-fill {{ $activeData['role'] === 'Coin Vendor' ? 'ph-storefront' : 'ph-headset' }}"></i>@endif
                            </span>
                            <div class="msg-chat__identity"><strong>{{ $activeData['name'] }}</strong><span>{{ __($activeData['role']) }} <i></i> @lang('Direct conversation')</span></div>
                            <button type="button" class="msg-ring-button" id="msgRingOpen" title="@lang('Ring them online')"><i class="ph-fill ph-bell-ringing"></i><span>@lang('Ring Me')</span></button>
                        </header>

                        <div class="msg-chat__privacy"><i class="ph-fill ph-shield-check"></i><span>@lang('Keep payments and coin trades on Winxpot. We will never ask you to share your password or verification code.')</span></div>

                        <div class="msg-thread" id="msgThreadMessages" aria-live="polite" aria-relevant="additions text">
                            @forelse($activeMessages as $message)
                                <div class="msg-message-row {{ $message['mine'] ? 'is-mine' : '' }}" data-message-id="{{ $message['id'] }}">
                                    @if(!$message['mine'])<span class="msg-mini-avatar">{{ mb_substr($activeData['name'], 0, 1) }}</span>@endif
                                    <div class="msg-message-stack">
                                        @if(($message['kind'] ?? 'text') === 'image' && $message['attachment_url'])
                                            <a class="msg-media" href="{{ $message['attachment_url'] }}" target="_blank" rel="noopener"><img src="{{ $message['attachment_url'] }}" alt="{{ $message['attachment_name'] ?: __('Image attachment') }}" loading="lazy"></a>
                                        @elseif(($message['kind'] ?? 'text') === 'video' && $message['attachment_url'])
                                            <video class="msg-video" src="{{ $message['attachment_url'] }}" controls playsinline preload="metadata"></video>
                                        @else
                                            <div class="msg-bubble">{{ $message['body'] }}</div>
                                        @endif
                                        <time class="msg-message-time">{{ $message['time'] }}</time>
                                    </div>
                                </div>
                            @empty
                                <div class="msg-thread-empty"><span><i class="ph-fill ph-paper-plane-tilt"></i></span><strong>@lang('Say hello')</strong><p>@lang('Your messages with this contact will appear here.')</p></div>
                            @endforelse
                            <div id="msgThreadEnd"></div>
                        </div>

                        <div class="msg-emoji-tray" id="msgEmojiTray" hidden>
                            @foreach(['😀','😂','😍','🥰','😎','🤔','😊','👏','🙏','🤝','❤️','🔥','💯','✅','🎉','🚀','💡','⚡','👍','👋'] as $emoji)
                                <button type="button" class="msg-emoji" data-emoji="{{ $emoji }}" aria-label="{{ $emoji }}">{{ $emoji }}</button>
                            @endforeach
                        </div>
                        <form class="msg-composer" id="msgThreadForm">
                            <input type="file" id="msgAttachment" accept="image/*,video/mp4,video/webm,video/quicktime" hidden>
                            <div class="msg-composer__tools">
                                <button type="button" id="msgEmojiToggle" title="@lang('Add emoji')" aria-label="@lang('Add emoji')"><i class="ph-bold ph-smiley"></i></button>
                                <button type="button" id="msgAttachmentButton" title="@lang('Attach an image or video')" aria-label="@lang('Attach an image or video')"><i class="ph-bold ph-paperclip"></i></button>
                            </div>
                            <textarea id="msgDraft" name="body" rows="1" maxlength="2000" placeholder="@lang('Write a message…')" autocomplete="off"></textarea>
                            <button type="submit" class="msg-send" id="msgSend" aria-label="@lang('Send message')"><i class="ph-bold ph-paper-plane-right"></i></button>
                        </form>
                        <div class="msg-composer-hint"><span id="msgComposerStatus">@lang('Messages are delivered securely')</span><span>@lang('Enter to send') <kbd>↵</kbd></span></div>
                    @else
                        <div class="msg-welcome">
                            <div class="msg-welcome__art"><span><i class="ph-fill ph-chats-teardrop"></i></span><i class="ph-fill ph-sparkle"></i></div>
                            <span class="msg-eyebrow">@lang('Winxpot messages')</span>
                            <h2>@lang('A better way to connect.')</h2>
                            <p>@lang('Choose a conversation from your inbox or start a private chat with a coin vendor or support agent.')</p>
                            @if($contacts->isEmpty())<div class="msg-welcome__note"><i class="ph-fill ph-info"></i>@lang('No vendors or chat agents are available right now. Please check back soon.')</div>@endif
                        </div>
                    @endif
                </main>
            </section>
        </div>
    </div>

    <div class="msg-modal-backdrop" id="msgNewModal" hidden>
        <section class="msg-modal" role="dialog" aria-modal="true" aria-labelledby="msgNewTitle">
            <button type="button" class="msg-modal__close" data-close-modal aria-label="@lang('Close')"><i class="ph-bold ph-x"></i></button>
            <span class="msg-modal__icon"><i class="ph-fill ph-chat-teardrop-text"></i></span>
            <span class="msg-eyebrow">@lang('Start a conversation')</span>
            <h2 id="msgNewTitle">@lang('Who would you like to reach?')</h2>
            <p>@lang('You can contact active coin vendors and Winxpot support agents here.')</p>
            @if($contacts->isNotEmpty())
                <form method="post" action="{{ route('user.messages.start') }}" id="msgStartForm" class="msg-start-form">
                    @csrf
                    <label for="msgContactSelect">@lang('Choose a contact')</label>
                    <select id="msgContactSelect" required>
                        <option value="" disabled selected>@lang('Select a vendor or support agent')</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact['type'] }}:{{ $contact['id'] }}">{{ $contact['name'] }} · {{ __($contact['role']) }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="target_type" id="msgTargetType">
                    <input type="hidden" name="target_id" id="msgTargetId">
                    <label for="msgFirstMessage">@lang('Your message')</label>
                    <textarea id="msgFirstMessage" name="body" maxlength="2000" rows="3" placeholder="@lang('Write a short introduction…')" required></textarea>
                    <button type="submit" class="msg-modal__submit"><i class="ph-bold ph-paper-plane-right"></i> @lang('Start chat')</button>
                </form>
            @else
                <div class="msg-welcome__note"><i class="ph-fill ph-info"></i>@lang('There are no available contacts at the moment.')</div>
            @endif
        </section>
    </div>

    @if($activeData)
        <div class="msg-modal-backdrop" id="msgRingModal" hidden>
            <section class="msg-modal msg-ring-modal" role="dialog" aria-modal="true" aria-labelledby="msgRingTitle">
                <button type="button" class="msg-modal__close" data-close-modal aria-label="@lang('Close')"><i class="ph-bold ph-x"></i></button>
                <span class="msg-modal__icon"><i class="ph-fill ph-bell-ringing"></i></span>
                <span class="msg-eyebrow">{{ $activeData['name'] }}</span>
                <h2 id="msgRingTitle">@lang('Get their attention')</h2>
                <p>@lang('Send a private notification asking them to come online and continue this chat.')</p>
                <button type="button" class="msg-ring-submit" id="msgRingSend"><i class="ph-fill ph-bell-ringing"></i><span>@lang('Ring online')</span></button>
                <div class="msg-ring-feedback" id="msgRingFeedback" aria-live="polite"></div>
                @php($sharedContacts = $activeData['contact_methods'] ?? [])
                @if($sharedContacts)
                    <div class="msg-contact-list">
                        <span class="msg-contact-list__title">@lang('Shared contact options')</span>
                        @if(!empty($sharedContacts['phone']))<a href="tel:{{ preg_replace('/[^0-9+]/', '', $sharedContacts['phone']) }}"><i class="ph-fill ph-phone"></i><span>@lang('Call')<small>{{ $sharedContacts['phone'] }}</small></span><i class="ph-bold ph-arrow-up-right"></i></a>@endif
                        @if(!empty($sharedContacts['whatsapp']))<a href="https://wa.me/{{ preg_replace('/\D/', '', $sharedContacts['whatsapp']) }}" target="_blank" rel="noopener noreferrer"><i class="ph-fill ph-whatsapp-logo"></i><span>WhatsApp<small>{{ $sharedContacts['whatsapp'] }}</small></span><i class="ph-bold ph-arrow-up-right"></i></a>@endif
                        @if(!empty($sharedContacts['telegram']))<a href="https://t.me/{{ ltrim($sharedContacts['telegram'], '@') }}" target="_blank" rel="noopener noreferrer"><i class="ph-fill ph-telegram-logo"></i><span>Telegram<small>{{ $sharedContacts['telegram'] }}</small></span><i class="ph-bold ph-arrow-up-right"></i></a>@endif
                        @if(!empty($sharedContacts['email']))<a href="mailto:{{ $sharedContacts['email'] }}"><i class="ph-fill ph-envelope-simple"></i><span>@lang('Email')<small>{{ $sharedContacts['email'] }}</small></span><i class="ph-bold ph-arrow-up-right"></i></a>@endif
                    </div>
                @else
                    <div class="msg-modal__safe"><i class="ph-fill ph-shield-check"></i>@lang('No direct contact options have been shared. Keep your conversation and any transactions on Winxpot.')</div>
                @endif
                <div class="msg-modal__warning"><i class="ph-fill ph-warning-circle"></i><span>@lang('Never share your password or verification codes. Complete all coin trades through Winxpot escrow.')</span></div>
            </section>
        </div>
    @endif

    <style>
        .msg-page-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin:0 0 18px}
        .msg-page-heading .nocturne-page-title{margin:3px 0 4px}
        .msg-page-heading p{margin:0;color:var(--nocturne-muted);font-size:13px;line-height:1.5}
        .msg-eyebrow{display:inline-flex;align-items:center;gap:7px;color:var(--nocturne-green);font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase}
        .msg-eyebrow i{font-size:15px}
        .msg-new-trigger{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:0 15px;border:1px solid rgba(0,239,135,.22);border-radius:12px;background:rgba(0,239,135,.1);color:var(--nocturne-green);font-size:12px;font-weight:700;cursor:pointer;transition:.18s ease}
        .msg-new-trigger:hover:not(:disabled){background:var(--nocturne-green);color:#063b29;transform:translateY(-1px)}
        .msg-new-trigger:disabled{opacity:.45;cursor:not-allowed}
        .msg-workspace{display:grid;grid-template-columns:minmax(250px,310px) minmax(0,1fr);height:min(75vh,760px);min-height:540px;overflow:hidden;border:1px solid var(--nocturne-line);border-radius:20px;background:linear-gradient(145deg,rgba(25,31,57,.98),rgba(15,20,39,.99));box-shadow:0 22px 55px rgba(0,0,0,.22)}
        .msg-sidebar{display:flex;min-width:0;flex-direction:column;border-right:1px solid var(--nocturne-line);background:rgba(11,14,31,.52)}
        .msg-sidebar__head{padding:17px 15px 12px;border-bottom:1px solid var(--nocturne-line)}
        .msg-sidebar__head>div{display:flex;align-items:center;justify-content:space-between;margin-bottom:13px}
        .msg-sidebar__title{color:var(--nocturne-text);font-size:14px;font-weight:700}
        .msg-sidebar__count{display:grid;min-width:22px;height:22px;place-items:center;padding:0 5px;border-radius:8px;background:var(--nocturne-raised);color:var(--nocturne-muted);font-size:10px;font-weight:700}
        .msg-search{display:flex;align-items:center;gap:8px;height:38px;padding:0 11px;border:1px solid var(--nocturne-line);border-radius:11px;background:rgba(8,11,25,.64);color:var(--nocturne-muted)}
        .msg-search i{font-size:15px}
        .msg-search input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:var(--nocturne-text);font-size:11.5px}
        .msg-search input::placeholder{color:#737d9e}
        .msg-conversation-list{flex:1;overflow:auto;padding:7px}
        .msg-conversation{display:flex;align-items:center;gap:10px;min-height:74px;margin-bottom:4px;padding:10px;border:1px solid transparent;border-radius:13px;color:var(--nocturne-text);text-decoration:none;transition:background .16s,border-color .16s}
        .msg-conversation:hover{background:rgba(35,42,71,.56)}
        .msg-conversation.is-active{border-color:rgba(0,239,135,.2);background:linear-gradient(105deg,rgba(0,239,135,.12),rgba(0,239,135,.035))}
        .msg-avatar{position:relative;display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;overflow:visible;border:1px solid rgba(0,239,135,.15);border-radius:50%;background:linear-gradient(145deg,#273451,#19233a);color:var(--nocturne-green);font-size:19px}
        .msg-avatar img{width:100%;height:100%;border-radius:50%;object-fit:cover}
        .msg-avatar--large{flex-basis:46px;width:46px;height:46px;font-size:20px}
        .msg-unread-dot{position:absolute;top:-1px;right:-1px;width:10px;height:10px;border:2px solid #11172b;border-radius:50%;background:var(--nocturne-green)}
        .msg-conversation__copy{display:flex;min-width:0;flex:1;flex-direction:column;gap:3px}
        .msg-conversation__top{display:flex;align-items:center;justify-content:space-between;gap:8px}
        .msg-conversation__top strong{overflow:hidden;color:var(--nocturne-text);font-size:12px;font-weight:650;text-overflow:ellipsis;white-space:nowrap}
        .msg-conversation__top time{flex:0 0 auto;color:#7782a4;font-size:9px}
        .msg-conversation__role{color:var(--nocturne-green);font-size:9px;font-weight:650}
        .msg-conversation__preview{overflow:hidden;color:var(--nocturne-muted);font-size:10.5px;text-overflow:ellipsis;white-space:nowrap}
        .msg-unread-count{display:grid;min-width:18px;height:18px;place-items:center;padding:0 4px;border-radius:7px;background:var(--nocturne-green);color:#063b29;font-size:9px;font-weight:800}
        .msg-sidebar__foot{display:flex;align-items:center;gap:7px;padding:11px 14px;border-top:1px solid var(--nocturne-line);color:#7782a4;font-size:9.5px}
        .msg-sidebar__foot i{color:var(--nocturne-green);font-size:13px}
        .msg-list-empty{display:grid;justify-items:center;gap:8px;padding:56px 18px;text-align:center}
        .msg-list-empty>span,.msg-thread-empty>span{display:grid;width:46px;height:46px;place-items:center;border:1px solid rgba(0,239,135,.14);border-radius:15px;background:rgba(0,239,135,.08);color:var(--nocturne-green);font-size:22px}
        .msg-list-empty strong,.msg-thread-empty strong{color:var(--nocturne-text);font-size:12px;font-weight:650}
        .msg-list-empty p,.msg-thread-empty p{max-width:210px;margin:0;color:var(--nocturne-muted);font-size:10.5px;line-height:1.6}
        .msg-chat{display:flex;min-width:0;min-height:0;flex-direction:column;background:radial-gradient(ellipse at 80% -15%,rgba(0,239,135,.06),transparent 40%)}
        .msg-chat__header{display:flex;align-items:center;gap:11px;min-height:70px;padding:11px 17px;border-bottom:1px solid var(--nocturne-line);background:rgba(18,23,44,.77)}
        .msg-chat__identity{display:flex;min-width:0;flex:1;flex-direction:column;gap:4px}
        .msg-chat__identity strong{overflow:hidden;color:var(--nocturne-text);font-size:13px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}
        .msg-chat__identity span{display:flex;align-items:center;gap:6px;color:var(--nocturne-muted);font-size:9.5px}
        .msg-chat__identity span i{width:4px;height:4px;border-radius:50%;background:#596582}
        .msg-ring-button{display:inline-flex;align-items:center;gap:7px;min-height:36px;padding:0 11px;border:1px solid rgba(0,239,135,.18);border-radius:11px;background:rgba(0,239,135,.08);color:var(--nocturne-green);font-size:10.5px;font-weight:650;cursor:pointer}
        .msg-ring-button:hover{background:rgba(0,239,135,.16)}
        .msg-ring-button i{font-size:15px}
        .msg-back-mobile{display:none}
        .msg-chat__privacy{display:flex;align-items:center;gap:8px;margin:11px 16px 0;padding:9px 11px;border:1px solid rgba(0,239,135,.1);border-radius:10px;background:rgba(0,239,135,.045);color:#9aa7c5;font-size:9.5px;line-height:1.45}
        .msg-chat__privacy i{flex:0 0 auto;color:var(--nocturne-green);font-size:14px}
        .msg-thread{display:flex;flex:1;min-height:0;flex-direction:column;gap:11px;overflow:auto;padding:17px 19px;scroll-behavior:smooth}
        .msg-message-row{display:flex;align-items:flex-end;gap:7px}
        .msg-message-row.is-mine{justify-content:flex-end}
        .msg-mini-avatar{display:grid;flex:0 0 24px;width:24px;height:24px;place-items:center;border-radius:50%;background:var(--nocturne-raised);color:#c2cbe4;font-size:9px;font-weight:700}
        .msg-message-stack{display:flex;max-width:min(76%,520px);flex-direction:column;align-items:flex-start;gap:4px}
        .msg-message-row.is-mine .msg-message-stack{align-items:flex-end}
        .msg-bubble{padding:10px 13px;border:1px solid rgba(144,158,205,.1);border-radius:15px 15px 15px 5px;background:linear-gradient(145deg,#222a47,#1b223b);color:#e9edfa;font-size:12px;line-height:1.55;white-space:pre-wrap;overflow-wrap:anywhere}
        .msg-message-row.is-mine .msg-bubble{border-color:rgba(0,239,135,.16);border-radius:15px 15px 5px 15px;background:linear-gradient(145deg,#00e985,#00c977);color:#063b29}
        .msg-message-time{padding:0 4px;color:#7480a0;font-size:9px}
        .msg-media,.msg-video{display:block;max-width:min(100%,360px);overflow:hidden;border:1px solid var(--nocturne-line);border-radius:14px;background:#080c1a}
        .msg-media img{display:block;max-width:100%;max-height:260px;object-fit:cover}
        .msg-video{width:min(100%,360px);max-height:260px}
        .msg-thread-empty{display:grid;flex:1;align-content:center;justify-items:center;gap:9px;text-align:center}
        .msg-emoji-tray{display:flex;flex-wrap:wrap;gap:3px;padding:8px 14px;border-top:1px solid var(--nocturne-line);background:rgba(15,20,39,.88)}
        .msg-emoji-tray[hidden]{display:none}
        .msg-emoji{display:grid;min-width:34px;height:34px;place-items:center;border:0;border-radius:9px;background:transparent;font-size:19px;cursor:pointer}
        .msg-emoji:hover{background:var(--nocturne-raised)}
        .msg-composer{display:flex;align-items:flex-end;gap:9px;margin:0 13px;padding:8px 9px;border:1px solid rgba(145,156,205,.19);border-radius:15px;background:rgba(9,13,28,.88);transition:border-color .18s}
        .msg-composer:focus-within{border-color:rgba(0,239,135,.42);box-shadow:0 0 0 3px rgba(0,239,135,.055)}
        .msg-composer__tools{display:flex;flex:0 0 auto;gap:2px}
        .msg-composer__tools button,.msg-send{display:grid;width:34px;height:34px;place-items:center;border:0;border-radius:10px;background:transparent;color:#8792b3;font-size:17px;cursor:pointer}
        .msg-composer__tools button:hover{background:var(--nocturne-raised);color:var(--nocturne-green)}
        .msg-composer textarea{width:100%;min-width:0;max-height:110px;min-height:34px;resize:none;padding:7px 2px;border:0;outline:0;background:transparent;color:var(--nocturne-text);font-size:12px;line-height:1.5}
        .msg-composer textarea::placeholder{color:#75809f}
        .msg-send{flex:0 0 auto;background:var(--nocturne-green);color:#063b29;transition:transform .16s,opacity .16s}
        .msg-send:hover{transform:translateY(-1px)}
        .msg-send:disabled{opacity:.45;cursor:not-allowed;transform:none}
        .msg-composer-hint{display:flex;justify-content:space-between;gap:10px;padding:6px 18px 9px;color:#727d9c;font-size:9px}
        .msg-composer-hint kbd{padding:1px 4px;border:1px solid var(--nocturne-line);border-radius:4px;color:#a7b1ca;font:inherit}
        .msg-welcome{display:flex;flex:1;flex-direction:column;align-items:center;justify-content:center;padding:30px;text-align:center}
        .msg-welcome__art{position:relative;display:grid;width:96px;height:96px;place-items:center;margin-bottom:19px;border:1px solid rgba(0,239,135,.15);border-radius:29px;background:radial-gradient(circle at 30% 20%,rgba(0,239,135,.2),transparent 62%),#171f39;box-shadow:0 17px 35px rgba(0,0,0,.18);color:var(--nocturne-green)}
        .msg-welcome__art>span{font-size:42px}
        .msg-welcome__art>.ph-sparkle{position:absolute;top:14px;right:13px;font-size:17px}
        .msg-welcome h2{margin:8px 0;color:var(--nocturne-text);font-size:21px;font-weight:700;letter-spacing:-.035em}
        .msg-welcome>p{max-width:330px;margin:0;color:var(--nocturne-muted);font-size:12px;line-height:1.7}
        .msg-welcome__note,.msg-modal__safe{display:flex;align-items:flex-start;gap:8px;margin-top:18px;padding:11px 13px;border:1px solid var(--nocturne-line);border-radius:12px;background:rgba(255,255,255,.025);color:#9aa5c4;font-size:10.5px;line-height:1.55;text-align:left}
        .msg-welcome__note i,.msg-modal__safe i{flex:0 0 auto;color:var(--nocturne-green);font-size:15px}
        .msg-modal-backdrop[hidden]{display:none}
        .msg-modal-backdrop{position:fixed;z-index:10050;inset:0;display:grid;place-items:center;padding:18px;background:rgba(3,5,14,.76);backdrop-filter:blur(10px)}
        .msg-modal{position:relative;width:min(100%,430px);max-height:min(90dvh,780px);overflow:auto;padding:27px;border:1px solid rgba(151,165,209,.19);border-radius:23px;background:radial-gradient(ellipse at 80% 0%,rgba(0,239,135,.1),transparent 38%),linear-gradient(145deg,#19203a,#10162b);box-shadow:0 28px 90px rgba(0,0,0,.5)}
        .msg-modal__close{position:absolute;top:14px;right:14px;display:grid;width:34px;height:34px;place-items:center;border:1px solid var(--nocturne-line);border-radius:10px;background:rgba(255,255,255,.04);color:#a8b2cd;font-size:16px;cursor:pointer}
        .msg-modal__icon{display:grid;width:43px;height:43px;place-items:center;margin-bottom:15px;border:1px solid rgba(0,239,135,.17);border-radius:14px;background:rgba(0,239,135,.1);color:var(--nocturne-green);font-size:20px}
        .msg-modal h2{margin:7px 0;color:var(--nocturne-text);font-size:18px;font-weight:700;letter-spacing:-.025em}
        .msg-modal>p{margin:0 0 18px;color:var(--nocturne-muted);font-size:11.5px;line-height:1.6}
        .msg-start-form{display:grid;gap:8px}
        .msg-start-form label{margin-top:5px;color:#a8b2cd;font-size:10.5px;font-weight:650}
        .msg-start-form select,.msg-start-form textarea{width:100%;padding:11px 12px;border:1px solid var(--nocturne-line);border-radius:11px;outline:0;background:#0d1225;color:var(--nocturne-text);font:inherit;font-size:11.5px}
        .msg-start-form textarea{resize:vertical;min-height:82px}
        .msg-start-form select:focus,.msg-start-form textarea:focus{border-color:rgba(0,239,135,.45)}
        .msg-modal__submit,.msg-ring-submit{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;min-height:43px;margin-top:8px;border:0;border-radius:12px;background:var(--nocturne-green);color:#063b29;font-size:11.5px;font-weight:750;cursor:pointer}
        .msg-ring-modal{text-align:center}
        .msg-ring-modal .msg-modal__icon{margin-right:auto;margin-left:auto}
        .msg-ring-modal .msg-ring-submit{margin:2px 0 7px}
        .msg-ring-feedback{min-height:17px;color:var(--nocturne-green);font-size:10.5px}
        .msg-contact-list{display:grid;gap:6px;margin-top:15px;text-align:left}
        .msg-contact-list__title{padding:0 2px 4px;color:#9aa5c4;font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
        .msg-contact-list a{display:flex;align-items:center;gap:10px;padding:9px 11px;border:1px solid var(--nocturne-line);border-radius:11px;background:rgba(255,255,255,.025);color:var(--nocturne-text);text-decoration:none}
        .msg-contact-list a>i:first-child{color:var(--nocturne-green);font-size:17px}
        .msg-contact-list a>i:last-child{margin-left:auto;color:#7782a2;font-size:12px}
        .msg-contact-list a span{display:grid;gap:2px;font-size:10.5px;font-weight:650}
        .msg-contact-list a small{color:var(--nocturne-muted);font-size:9.5px;font-weight:450}
        .msg-modal__warning{display:flex;align-items:flex-start;gap:8px;margin-top:13px;padding:10px;border:1px solid rgba(255,190,77,.18);border-radius:11px;background:rgba(255,190,77,.055);color:#d2bd91;font-size:9.5px;line-height:1.5;text-align:left}
        .msg-modal__warning i{flex:0 0 auto;color:#ffc45b;font-size:14px}
        .msg-notice{margin:0 0 14px;padding:11px 13px;border-radius:11px;font-size:11px}
        .msg-notice--success{border:1px solid rgba(0,239,135,.2);background:rgba(0,239,135,.08);color:#9af0c9}
        .msg-notice--error{border:1px solid rgba(255,95,125,.24);background:rgba(255,95,125,.08);color:#ff9bad}
        @media(max-width:900px){.msg-workspace{grid-template-columns:minmax(230px,275px) minmax(0,1fr)}}
        @media(max-width:700px){
            .msg-page-heading{align-items:center;margin-bottom:13px}
            .msg-page-heading p{max-width:380px;font-size:11.5px}
            .msg-new-trigger{min-width:42px;padding:0 12px}.msg-new-trigger span{display:none}
            .msg-workspace{display:block;height:calc(100dvh - 230px);min-height:450px;max-height:780px;border-radius:17px}
            .msg-sidebar,.msg-chat{height:100%}
            .msg-workspace[data-mobile-view="chat"] .msg-sidebar{display:none}
            .msg-workspace[data-mobile-view="list"] .msg-chat{display:none}
            .msg-back-mobile{display:grid;flex:0 0 36px;width:36px;height:36px;place-items:center;border:1px solid var(--nocturne-line);border-radius:10px;background:rgba(255,255,255,.035);color:#c2cbe4;font-size:17px}
            .msg-chat__header{min-height:64px;padding:9px 10px;gap:8px}
            .msg-ring-button{width:37px;min-width:37px;justify-content:center;padding:0}.msg-ring-button span{display:none}
            .msg-chat__privacy{margin:8px 9px 0;padding:8px;font-size:9px}
            .msg-thread{padding:14px 10px}
            .msg-message-stack{max-width:84%}
            .msg-composer{margin:0 8px;padding:6px}
            .msg-composer__tools{gap:0}
            .msg-composer__tools button,.msg-send{width:32px;height:34px}
            .msg-composer-hint{padding-right:11px;padding-left:11px}
        }
        @media(max-width:420px){.msg-page-heading p{max-width:235px}.msg-avatar--large{flex-basis:40px;width:40px;height:40px}.msg-chat__identity strong{font-size:12px}.msg-chat__identity span{font-size:8px}.msg-modal{padding:23px 19px}}
        @media(prefers-reduced-motion:reduce){.msg-conversation,.msg-bubble,.msg-send,.msg-new-trigger{transition:none}}
    </style>

    <script>
    (() => {
        const workspace = document.getElementById('msgWorkspace');
        const newModal = document.getElementById('msgNewModal');
        const ringModal = document.getElementById('msgRingModal');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
        const closeModal = (modal) => { if (modal) modal.hidden = true; };

        document.getElementById('msgNewOpen')?.addEventListener('click', () => { if (newModal) newModal.hidden = false; });
        document.getElementById('msgRingOpen')?.addEventListener('click', () => { if (ringModal) ringModal.hidden = false; });
        document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.msg-modal-backdrop'))));
        [newModal, ringModal].filter(Boolean).forEach((modal) => modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(modal); }));
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape') [newModal, ringModal].forEach(closeModal); });

        const contactSelect = document.getElementById('msgContactSelect');
        document.getElementById('msgStartForm')?.addEventListener('submit', (event) => {
            const [type, id] = (contactSelect?.value || '').split(':');
            if (!type || !id) { event.preventDefault(); contactSelect?.focus(); return; }
            document.getElementById('msgTargetType').value = type;
            document.getElementById('msgTargetId').value = id;
        });

        document.getElementById('msgSearch')?.addEventListener('input', (event) => {
            const query = event.target.value.trim().toLowerCase();
            document.querySelectorAll('.msg-conversation').forEach((row) => { row.hidden = query && !row.dataset.search.includes(query); });
        });

        document.getElementById('msgBackToList')?.addEventListener('click', () => {
            workspace.dataset.mobileView = 'list';
        });

        const thread = document.getElementById('msgThreadMessages');
        const conversationId = @json($activeData['id'] ?? null);
        const participantName = @json($activeData['name'] ?? '');
        const pollUrl = conversationId ? @json($activeData ? route('user.messages.poll', $activeData['id']) : null) : null;
        const listUrl = @json(route('user.messages.list'));
        const replyUrl = conversationId ? @json($activeData ? route('user.messages.reply', $activeData['id']) : null) : null;
        const uploadUrl = conversationId ? @json($activeData ? route('user.messages.attachment.upload', $activeData['id']) : null) : null;
        const ringUrl = conversationId ? @json($activeData ? route('user.messages.ring', $activeData['id']) : null) : null;
        const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));

        function renderMessages(messages) {
            if (!thread) return;
            const nearBottom = thread.scrollHeight - thread.scrollTop - thread.clientHeight < 90;
            thread.replaceChildren();
            if (!messages.length) {
                const empty = document.createElement('div'); empty.className = 'msg-thread-empty';
                empty.innerHTML = '<span><i class="ph-fill ph-paper-plane-tilt"></i></span><strong>@lang('Say hello')</strong><p>@lang('Your messages with this contact will appear here.')</p>';
                thread.append(empty); return;
            }
            messages.forEach((message) => {
                const row = document.createElement('div'); row.className = 'msg-message-row' + (message.mine ? ' is-mine' : ''); row.dataset.messageId = message.id;
                if (!message.mine) { const avatar = document.createElement('span'); avatar.className = 'msg-mini-avatar'; avatar.textContent = (participantName || '?').slice(0, 1); row.append(avatar); }
                const stack = document.createElement('div'); stack.className = 'msg-message-stack';
                if (message.kind === 'image' && message.attachment_url) {
                    const link = document.createElement('a'); link.className = 'msg-media'; link.href = message.attachment_url; link.target = '_blank'; link.rel = 'noopener';
                    const image = document.createElement('img'); image.src = message.attachment_url; image.alt = message.attachment_name || 'Image attachment'; image.loading = 'lazy'; link.append(image); stack.append(link);
                } else if (message.kind === 'video' && message.attachment_url) {
                    const video = document.createElement('video'); video.className = 'msg-video'; video.src = message.attachment_url; video.controls = true; video.playsInline = true; video.preload = 'metadata'; stack.append(video);
                } else {
                    const bubble = document.createElement('div'); bubble.className = 'msg-bubble'; bubble.textContent = message.body || ''; stack.append(bubble);
                }
                const time = document.createElement('time'); time.className = 'msg-message-time'; time.textContent = message.time || ''; stack.append(time);
                row.append(stack); thread.append(row);
            });
            const end = document.createElement('div'); end.id = 'msgThreadEnd'; thread.append(end);
            if (nearBottom) thread.scrollTop = thread.scrollHeight;
        }

        async function pollMessages() {
            if (!pollUrl || !thread || document.hidden) return;
            try {
                const response = await fetch(pollUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (response.ok) { const data = await response.json(); renderMessages(data.messages || []); }
            } catch (_) {}
        }
        if (pollUrl) {
            let polling = false;
            const poll = async () => {
                if (polling) return;
                polling = true;
                await pollMessages();
                polling = false;
                window.setTimeout(poll, document.hidden ? 9000 : 3500);
            };
            poll();
            document.addEventListener('visibilitychange', () => { if (!document.hidden) pollMessages(); });
        }

        const search = document.getElementById('msgSearch');
        async function refreshConversations() {
            try {
                const response = await fetch(listUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) return;
                const data = await response.json();
                const list = document.getElementById('msgConversationList');
                if (!list || !Array.isArray(data.conversations)) return;
                list.replaceChildren();
                data.conversations.forEach((conversation) => {
                    const link = document.createElement('a'); link.className = 'msg-conversation' + (String(conversation.id) === String(conversationId) ? ' is-active' : '');
                    link.href = @json(url('/user/messages')) + '/' + encodeURIComponent(conversation.id);
                    link.dataset.search = `${conversation.name} ${conversation.role} ${conversation.preview || ''}`.toLowerCase();
                    const avatar = document.createElement('span'); avatar.className = 'msg-avatar';
                    if (conversation.avatar) { const image = document.createElement('img'); image.src = conversation.avatar; image.alt = ''; avatar.append(image); }
                    else { const icon = document.createElement('i'); icon.className = 'ph-fill ' + (conversation.role === 'Coin Vendor' ? 'ph-storefront' : 'ph-headset'); avatar.append(icon); }
                    if (conversation.unread) { const dot = document.createElement('b'); dot.className = 'msg-unread-dot'; avatar.append(dot); }
                    const copy = document.createElement('span'); copy.className = 'msg-conversation__copy';
                    const top = document.createElement('span'); top.className = 'msg-conversation__top';
                    const name = document.createElement('strong'); name.textContent = conversation.name;
                    const time = document.createElement('time'); time.textContent = conversation.time || '';
                    top.append(name, time);
                    const role = document.createElement('span'); role.className = 'msg-conversation__role'; role.textContent = conversation.role;
                    const preview = document.createElement('span'); preview.className = 'msg-conversation__preview'; preview.textContent = conversation.preview || 'Start a conversation';
                    copy.append(top, role, preview); link.append(avatar, copy);
                    if (conversation.unread) { const badge = document.createElement('span'); badge.className = 'msg-unread-count'; badge.textContent = conversation.unread > 99 ? '99+' : conversation.unread; link.append(badge); }
                    link.hidden = Boolean(search?.value.trim()) && !link.dataset.search.includes(search.value.trim().toLowerCase());
                    list.append(link);
                });
            } catch (_) {}
        }
        if (conversationId) window.setInterval(refreshConversations, 9000);

        const form = document.getElementById('msgThreadForm');
        const draft = document.getElementById('msgDraft');
        const sendButton = document.getElementById('msgSend');
        const status = document.getElementById('msgComposerStatus');
        let sending = false;
        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const body = draft.value.trim();
            if (!body || sending) return;
            sending = true; sendButton.disabled = true; status.textContent = '@lang('Sending…')';
            try {
                const response = await fetch(replyUrl, { method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-CSRF-TOKEN':csrf,Accept:'application/json'}, credentials:'same-origin', body: new URLSearchParams({body}) });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || '@lang('Message could not be sent. Try again.')');
                draft.value = ''; draft.style.height = 'auto'; status.textContent = '@lang('Messages are delivered securely')';
                await pollMessages(); refreshConversations();
            } catch (error) { status.textContent = error.message || '@lang('Connection issue. Please try again.')'; }
            finally { sending = false; sendButton.disabled = !draft.value.trim(); draft.focus(); }
        });
        draft?.addEventListener('input', () => { draft.style.height = 'auto'; draft.style.height = Math.min(draft.scrollHeight, 110) + 'px'; sendButton.disabled = !draft.value.trim() || sending; });
        draft?.addEventListener('keydown', (event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); form.requestSubmit(); } });

        const emojiTray = document.getElementById('msgEmojiTray');
        document.getElementById('msgEmojiToggle')?.addEventListener('click', () => { emojiTray.hidden = !emojiTray.hidden; });
        emojiTray?.addEventListener('click', (event) => {
            const button = event.target.closest('[data-emoji]'); if (!button || !draft) return;
            const start = draft.selectionStart, end = draft.selectionEnd, emoji = button.dataset.emoji;
            draft.setRangeText(emoji, start, end, 'end'); draft.dispatchEvent(new Event('input')); draft.focus();
        });
        document.getElementById('msgAttachmentButton')?.addEventListener('click', () => document.getElementById('msgAttachment').click());
        document.getElementById('msgAttachment')?.addEventListener('change', async (event) => {
            const file = event.target.files?.[0]; if (!file || !uploadUrl) return;
            const payload = new FormData(); payload.append('attachment', file); sending = true; status.textContent = '@lang('Uploading media…')'; sendButton.disabled = true;
            try {
                const response = await fetch(uploadUrl, { method:'POST', headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'}, credentials:'same-origin', body:payload });
                const data = await response.json(); if (!response.ok) throw new Error(data.message || '@lang('Upload failed. Try a smaller image or video.')');
                status.textContent = '@lang('Media sent securely')'; await pollMessages(); refreshConversations();
            } catch (error) { status.textContent = error.message || '@lang('Upload failed. Please try again.')'; }
            finally { sending = false; sendButton.disabled = !draft.value.trim(); event.target.value = ''; }
        });

        document.getElementById('msgRingSend')?.addEventListener('click', async (event) => {
            const button = event.currentTarget, feedback = document.getElementById('msgRingFeedback');
            button.disabled = true; button.querySelector('span').textContent = '@lang('Sending ring…')';
            try {
                const response = await fetch(ringUrl, {method:'POST',headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'},credentials:'same-origin'});
                const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.message || '@lang('Could not send the notification. Try again shortly.')');
                button.querySelector('span').textContent = '@lang('They have been notified')'; feedback.textContent = '@lang('Your contact will receive a private notification to come online.')';
            } catch (error) { button.disabled = false; button.querySelector('span').textContent = '@lang('Ring online')'; feedback.textContent = error.message || '@lang('Could not send the notification.')'; }
        });
    })();
    </script>
@endsection
