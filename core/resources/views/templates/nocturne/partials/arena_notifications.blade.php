@forelse($items as $item)
    <div class="nocturne-arena-notif {{ $item->read_at ? '' : 'is-unread' }}">
        <span class="nocturne-arena-notif__avatar">@if($item->actor?->avatarUrl())<img src="{{ $item->actor->avatarUrl() }}" alt="">@else<i class="ph-fill ph-bell"></i>@endif</span>
        <div>
            <p>{{ $item->message }}</p>
            <small>{{ diffForHumans($item->created_at) }}</small>
        </div>
    </div>
@empty
    <p class="nocturne-arena-notif-empty">@lang('No notifications yet.')</p>
@endforelse
