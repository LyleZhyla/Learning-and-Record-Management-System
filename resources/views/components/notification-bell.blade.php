<details class="notification-menu">
    <summary class="notification-bell" aria-label="Notifications{{ $unreadCount ? ': '.$unreadCount.' unread' : '' }}" data-notification-count="{{ $unreadCount }}">🔔@if($unreadCount)<span>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif</summary>
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
            @foreach($eventNotifications as $notification)
                <a class="notification-item notification-event {{ $notification->read_at ? '' : 'unread' }}" href="{{ route('notifications.events.open', $notification) }}">
                    <i></i>
                    <div><strong>{{ $notification->title }}</strong><p>{{ str($notification->body)->limit(90) }}</p><small>{{ $notification->categoryLabel() }} · {{ $notification->created_at->diffForHumans() }}</small></div>
                    <span class="notification-open" aria-hidden="true">→</span>
                </a>
            @endforeach
            @foreach($notifications as $notification)
                <a class="notification-item notification-announcement {{ $notification->is_read ? '' : 'unread' }}" href="{{ route('notifications.announcements.open', $notification) }}"><i></i><div><strong>{{ $notification->title }}</strong><p>{{ str($notification->body)->limit(90) }}</p><small>Announcement · {{ $notification->component?->code ?? 'All components' }} · {{ $notification->published_at?->diffForHumans() }}</small></div><span class="notification-open" aria-hidden="true">→</span></a>
            @endforeach
            @if($messageNotifications->isEmpty() && $eventNotifications->isEmpty() && $notifications->isEmpty())<div class="notification-empty">No notifications yet.</div>@endif
        </div>
    </div>
</details>
