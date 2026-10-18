@extends('Template::layouts.master')
@section('content')
    <div class="nocturne-account">
        @include('Template::partials.account_nav')
        <div class="nocturne-account-content nocturne-support-page">
            <div class="nocturne-support-heading">
                <div><span class="nocturne-support-eyebrow"><i class="ph-bold ph-headset"></i> WINXPOT CARE</span><h1 class="nocturne-page-title">Support center</h1><p>Your conversations and support requests, together in one place.</p></div>
                <a class="nocturne-support-new" href="{{ route('ticket.open') }}"><i class="ph-bold ph-plus"></i> New ticket</a>
            </div>

            @if($supports->count())
                <div class="nocturne-support-ticket-list">
                    @foreach($supports as $support)
                        <a class="nocturne-support-ticket-card" href="{{ route('ticket.view', $support->ticket) }}">
                            <span class="nocturne-support-ticket-icon"><i class="ph-bold {{ $support->channel === 'live' ? 'ph-chat-circle-dots' : 'ph-ticket' }}"></i></span>
                            <span class="nocturne-support-ticket-copy"><strong>{{ __($support->subject) }}</strong><small>Ticket #{{ $support->ticket }} <span>·</span> Updated {{ diffForHumans($support->last_reply) }}</small></span>
                            <span class="nocturne-support-ticket-status {{ $support->status == Status::TICKET_CLOSE ? 'is-closed' : ($support->status == Status::TICKET_ANSWER ? 'is-answered' : 'is-open') }}">{{ $support->status == Status::TICKET_CLOSE ? 'Closed' : ($support->status == Status::TICKET_ANSWER ? 'Answered' : 'Open') }}</span>
                            <i class="ph-bold ph-caret-right nocturne-support-ticket-arrow"></i>
                        </a>
                    @endforeach
                </div>
                <div class="mt-4">{{ paginateLinks($supports) }}</div>
            @else
                <div class="nocturne-support-empty"><span><i class="ph-bold ph-chats-circle"></i></span><h2>No conversations yet</h2><p>Start a live chat or open a support ticket. Your full conversation history will appear here.</p><a class="nocturne-support-new" href="{{ route('ticket.open') }}"><i class="ph-bold ph-plus"></i> Open a ticket</a></div>
            @endif
        </div>
    </div>
@endsection
