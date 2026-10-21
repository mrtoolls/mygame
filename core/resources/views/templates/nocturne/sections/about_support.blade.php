@php $supportContent = getContent('about_support.content', true)?->data_values; @endphp
<section class="nocturne-about-support nocturne-about-section">
    <div class="nocturne-about-support__copy"><h2>{{ __($supportContent->heading ?? 'Do you have a question?') }}</h2><p>{{ __($supportContent->subheading ?? 'Write us and our support team will help you.') }}</p></div>
    <div class="nocturne-about-support__team">
        <span class="nocturne-about-support__avatars">
            @foreach(['agent_one', 'agent_two', 'agent_three', 'agent_four'] as $agentImage)
                <span>@if(!empty($supportContent->$agentImage))<img src="{{ frontendImage('about_support', $supportContent->$agentImage, '64x64') }}" alt="Support agent">@else<i class="ph-fill ph-user"></i>@endif</span>
            @endforeach
        </span>
        <strong>{{ __($supportContent->reply_time ?? 'We are here when you need us') }} <i class="ph-fill ph-lightning"></i></strong>
    </div>
    <a class="nocturne-about-support__button" href="{{ $supportContent->button_url ?: route('ticket.index') }}"><i class="ph-fill ph-chats-circle"></i>{{ __($supportContent->button_name ?? 'Support') }}</a>
</section>
