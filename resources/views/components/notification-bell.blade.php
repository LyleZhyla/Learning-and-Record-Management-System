<details class="notification-menu">
    <summary class="notification-bell" aria-label="Notifications{{ $unreadCount ? ': '.$unreadCount.' unread' : '' }}">🔔@if($unreadCount)<span>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif</summary>
    <div class="notification-panel">
        <div class="notification-heading"><div><strong>Notifications</strong><small>{{ $unreadCount }} unread</small></div>@if($unreadCount)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit">Mark all read</button></form>@endif</div>
        <div class="notification-list">
            @foreach($messageNotifications as $message)
                <a class="notification-item notification-message unread" href="{{ route($messageRoutePrefix.'.messages.index', ['contact' => $message->sender]) }}" aria-label="Open message from {{ $message->sender->name }}">
                    <i></i>
                    <div><strong>New message from {{ $message->sender->name }}</strong><p>{{ str($message->body)->limit(90) }}</p><small>{{ $message->section?->code ?? 'NSTP message' }} · {{ $message->unread_from_sender }} unread · {{ $message->created_at->diffForHumans() }}</small></div>
                    <span class="notification-open" aria-hidden="true">→</span>
                </a>
            @endforeach
            @foreach($notifications as $notification)
                <article class="notification-item {{ $notification->is_read ? '' : 'unread' }}"><i></i><div><strong>{{ $notification->title }}</strong><p>{{ str($notification->body)->limit(90) }}</p><small>{{ $notification->component?->code ?? 'All components' }} · {{ $notification->published_at?->diffForHumans() }}</small></div>@unless($notification->is_read)<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<button type="submit" aria-label="Mark {{ $notification->title }} as read">✓</button></form>@endunless</article>
            @endforeach
            @if($messageNotifications->isEmpty() && $notifications->isEmpty())<div class="notification-empty">No notifications yet.</div>@endif
        </div>
    </div>
</details>
