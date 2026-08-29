<details class="notification-menu">
    <summary class="notification-bell" aria-label="Notifications{{ $unreadCount ? ': '.$unreadCount.' unread' : '' }}">🔔@if($unreadCount)<span>{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif</summary>
    <div class="notification-panel">
        <div class="notification-heading"><div><strong>Notifications</strong><small>{{ $unreadCount }} unread</small></div>@if($unreadCount)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit">Mark all read</button></form>@endif</div>
        <div class="notification-list">@forelse($notifications as $notification)<article class="notification-item {{ $notification->is_read ? '' : 'unread' }}"><i></i><div><strong>{{ $notification->title }}</strong><p>{{ str($notification->body)->limit(90) }}</p><small>{{ $notification->component?->code ?? 'All components' }} · {{ $notification->published_at?->diffForHumans() }}</small></div>@unless($notification->is_read)<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<button type="submit" aria-label="Mark {{ $notification->title }} as read">✓</button></form>@endunless</article>@empty<div class="notification-empty">No notifications yet.</div>@endforelse</div>
    </div>
</details>
