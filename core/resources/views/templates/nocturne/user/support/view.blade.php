@extends('Template::layouts.' . $layout)
@section('content')
    @if ($layout == 'frontend')
        <div class="container py-5 nocturne-support-page">
    @else
        <div class="nocturne-account">
            @include('Template::partials.account_nav')
            <div class="nocturne-account-content nocturne-support-page">
    @endif
        <div class="nocturne-support-thread-head">
            <div><a class="nocturne-support-back-link" href="{{ route('ticket.index') }}"><i class="ph-bold ph-arrow-left"></i> All conversations</a><span class="nocturne-support-eyebrow"><i class="ph-bold ph-headset"></i> WINXPOT CARE</span><h1 class="nocturne-page-title">{{ __($myTicket->subject) }}</h1><p>Ticket #{{ $myTicket->ticket }} <span>·</span> Updated {{ diffForHumans($myTicket->last_reply) }}</p></div>
            <span class="nocturne-support-ticket-status {{ $myTicket->status == Status::TICKET_CLOSE ? 'is-closed' : ($myTicket->status == Status::TICKET_ANSWER ? 'is-answered' : 'is-open') }}">{{ $myTicket->status == Status::TICKET_CLOSE ? 'Closed' : ($myTicket->status == Status::TICKET_ANSWER ? 'Answered' : 'Open') }}</span>
        </div>

        <div class="nocturne-support-thread">
            <div class="nocturne-support-thread__messages">
                @forelse($messages as $message)
                    @php
                        $senderType = $message->sender_type ?: ($message->admin_id ? 'admin' : 'user');
                        $isCustomer = $senderType === 'user';
                        $senderName = $message->supportAgent?->name ?: ($message->admin?->name ?: ($senderType === 'bot' ? 'Winxpot Assistant' : ($isCustomer ? ($myTicket->user?->username ?: $myTicket->name) : 'Winxpot Support')));
                    @endphp
                    <article class="nocturne-support-message {{ $isCustomer ? 'is-customer' : 'is-team' }}">
                        @if(!$isCustomer)
                            <span class="nocturne-support-message__avatar">@if($message->supportAgent?->photo)<img src="{{ asset('assets/images/support-agents/' . $message->supportAgent->photo) }}" alt="">@else<i class="ph-bold ph-headset"></i>@endif</span>
                        @endif
                        <div class="nocturne-support-message__body"><div class="nocturne-support-message__meta"><strong>{{ $senderName }}</strong><time>{{ showDateTime($message->created_at, 'M j, Y · H:i') }}</time></div><div class="nocturne-support-message__bubble">{{ $message->message }}</div>
                            @if($message->attachments->count())<div class="nocturne-support-message__files">@foreach($message->attachments as $file)<a href="{{ route('ticket.download', encrypt($file->id)) }}"><i class="ph-bold ph-paperclip"></i> Attachment</a>@endforeach</div>@endif
                        </div>
                        @if($isCustomer)<span class="nocturne-support-message__avatar is-user"><i class="ph-bold ph-user"></i></span>@endif
                    </article>
                @empty
                    <div class="nocturne-support-empty"><h2>Conversation started</h2><p>Send a reply below and our team will get back to you.</p></div>
                @endforelse
            </div>

            @if($myTicket->status != Status::TICKET_CLOSE)
                <form method="post" class="nocturne-support-reply disableSubmission" action="{{ route('ticket.reply', $myTicket->id) }}" enctype="multipart/form-data">
                    @csrf
                    <textarea name="message" rows="3" class="form-control form--control" placeholder="Write a reply…" required>{{ old('message') }}</textarea>
                    <div class="nocturne-support-reply__actions"><button type="button" class="nocturne-support-attach addAttachment"><i class="ph-bold ph-paperclip"></i> Attach</button><div class="fileUploadsContainer"></div><button type="submit" class="nocturne-support-submit"><i class="ph-bold ph-paper-plane-tilt"></i> Send reply</button></div>
                </form>
            @else
                <div class="nocturne-support-closed"><i class="ph-bold ph-lock-key"></i> This ticket is closed. Open a new ticket if you still need help.</div>
            @endif
        </div>
        @if(auth()->check() && $myTicket->user_id == auth()->id() && $myTicket->status != Status::TICKET_CLOSE)
            <form method="post" action="{{ route('ticket.close', $myTicket->id) }}" class="nocturne-support-close-form">@csrf<button type="submit" class="nocturne-support-close-ticket"><i class="ph-bold ph-check-circle"></i> Close this ticket</button></form>
        @endif
    @if ($layout == 'frontend')
        </div>
    @else
            </div>
        </div>
    @endif
@endsection
@push('script')
    <script>
        (function ($) {
            let added = 0;
            $('.addAttachment').on('click', function () {
                if (added >= 5) return;
                added++;
                $('.fileUploadsContainer').append('<div class="nocturne-support-file"><input type="file" name="attachments[]" class="form-control form--control" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" required><button type="button" class="removeFile" aria-label="Remove attachment">×</button></div>');
                if (added >= 5) $('.addAttachment').prop('disabled', true);
            });
            $(document).on('click', '.removeFile', function () { $(this).closest('.nocturne-support-file').remove(); added--; $('.addAttachment').prop('disabled', false); });
        })(jQuery);
    </script>
@endpush
