@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content">
            <h1 class="nocturne-page-title">@lang('Messages')</h1>

            <div class="msg-page">
                <section class="msg-section">
                    <div class="msg-section__head">
                        <div>
                            <h2>@lang('Start a conversation')</h2>
                            <p>@lang('Message a Coin Vendor or support agent directly. You can\'t message other members.')</p>
                        </div>
                    </div>
                    @if($contacts->isEmpty())
                        <div class="msg-empty"><i class="ph-fill ph-chats-circle"></i><p>@lang('No vendors or agents are available to message right now.')</p></div>
                    @else
                        <form method="post" action="{{ route('user.messages.start') }}" class="msg-start-form">
                            @csrf
                            <div class="msg-field">
                                <label>@lang('Message to')</label>
                                <select id="msg-contact-select" required>
                                    <option value="" disabled selected>@lang('Choose a vendor or agent')</option>
                                    @foreach($contacts as $contact)
                                        <option value="{{ $contact['type'] }}:{{ $contact['id'] }}">{{ $contact['name'] }} · {{ __($contact['role']) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="msg-field msg-field--grow">
                                <label>@lang('Message')</label>
                                <input type="text" name="body" maxlength="2000" placeholder="@lang('Write your first message…')" required>
                            </div>
                            <input type="hidden" name="target_type" id="msg-target-type">
                            <input type="hidden" name="target_id" id="msg-target-id">
                            <button type="submit" class="msg-btn msg-btn--primary"><i class="ph-bold ph-paper-plane-right"></i> @lang('Send')</button>
                        </form>
                    @endif
                </section>

                <section class="msg-section">
                    <div class="msg-section__head"><div><h2>@lang('Your conversations')</h2></div></div>
                    @if($conversations->isEmpty())
                        <div class="msg-empty"><i class="ph-fill ph-chat-circle-dots"></i><p>@lang('No conversations yet.')</p></div>
                    @else
                        <div class="msg-list">
                            @foreach($conversations as $conversation)
                                <a href="{{ route('user.messages.show', $conversation['id']) }}" class="msg-list__row">
                                    <span class="msg-list__avatar"><i class="ph-fill {{ $conversation['role'] === 'Coin Vendor' ? 'ph-storefront' : 'ph-headset' }}"></i></span>
                                    <div class="msg-list__body">
                                        <span class="msg-list__title">{{ $conversation['name'] }} <small>{{ $conversation['role'] }}</small></span>
                                        <span class="msg-list__preview">{{ \Illuminate\Support\Str::limit($conversation['preview'], 70) }}</span>
                                    </div>
                                    <div class="msg-list__trailing">
                                        <span class="msg-list__time">{{ $conversation['time'] }}</span>
                                        @if($conversation['unread'])<span class="msg-badge">{{ $conversation['unread'] }}</span>@endif
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </div>
    </div>

    <style>
        .msg-page{display:flex;flex-direction:column;gap:18px}
        .msg-section{padding:18px;border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:18px;background:var(--nocturne-panel,#151a31)}
        .msg-section__head h2{margin:0 0 4px;color:var(--nocturne-text,#f5f6ff);font-size:16.5px;font-weight:800}
        .msg-section__head p{margin:0 0 14px;color:var(--nocturne-muted,#838bb4);font-size:12.5px}
        .msg-start-form{display:flex;flex-wrap:wrap;gap:10px;align-items:end}
        .msg-field{display:flex;flex-direction:column;gap:6px;min-width:180px}
        .msg-field--grow{flex:1 1 260px}
        .msg-field label{color:var(--nocturne-muted,#838bb4);font-size:11.5px;font-weight:700}
        .msg-field select,.msg-field input{height:44px;padding:0 13px;border:1px solid var(--nocturne-line,rgba(150,165,210,.16));border-radius:12px;background:#0c0f22;color:var(--nocturne-text,#f5f6ff);font-size:13.5px}
        .msg-btn{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 18px;border:0;border-radius:12px;font-size:13.5px;font-weight:800;cursor:pointer}
        .msg-btn--primary{background:var(--nocturne-green,#00ef87);color:#063b29}
        .msg-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:30px 10px;color:var(--nocturne-muted,#838bb4);text-align:center}
        .msg-empty i{font-size:30px;opacity:.6}
        .msg-list{display:flex;flex-direction:column;gap:6px}
        .msg-list__row{display:flex;align-items:center;gap:12px;padding:12px;border-radius:14px;text-decoration:none;color:var(--nocturne-text,#f5f6ff);transition:background .15s ease}
        .msg-list__row:hover{background:var(--nocturne-raised,#232a47)}
        .msg-list__avatar{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:50%;background:var(--nocturne-raised,#232a47);color:var(--nocturne-green,#00ef87);font-size:19px}
        .msg-list__body{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
        .msg-list__title{font-size:14px;font-weight:800}
        .msg-list__title small{margin-left:6px;color:var(--nocturne-muted,#838bb4);font-size:11px;font-weight:700}
        .msg-list__preview{color:var(--nocturne-muted,#838bb4);font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .msg-list__trailing{display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex:0 0 auto}
        .msg-list__time{color:var(--nocturne-muted,#838bb4);font-size:11px}
        .msg-badge{display:inline-grid;place-items:center;min-width:19px;height:19px;padding:0 5px;border-radius:9px;background:#ff4d67;color:#fff;font-size:10.5px;font-weight:800}
        @media(max-width:600px){.msg-start-form{flex-direction:column;align-items:stretch}.msg-field{min-width:0}}
    </style>
    <script>
        (() => {
            const select = document.getElementById('msg-contact-select');
            const typeInput = document.getElementById('msg-target-type');
            const idInput = document.getElementById('msg-target-id');
            if (!select) return;
            select.addEventListener('change', () => {
                const [type, id] = (select.value || '').split(':');
                typeInput.value = type || '';
                idInput.value = id || '';
            });
        })();
    </script>
@endsection
