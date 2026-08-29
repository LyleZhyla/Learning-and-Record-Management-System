<?php

namespace App\View\Components;

use App\Services\NotificationService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class NotificationBell extends Component
{
    public function __construct() {}

    public function render(): View|Closure|string
    {
        $user = auth()->user();
        $query = app(NotificationService::class)->visibleQuery($user);
        $unreadCount = (clone $query)->whereDoesntHave('readers', fn ($readers) => $readers->whereKey($user->id))->count();
        $notifications = $query->with(['author', 'component'])
            ->withExists(['readers as is_read' => fn ($readers) => $readers->whereKey($user->id)])
            ->latest('published_at')->limit(6)->get();

        return view('components.notification-bell', compact('notifications', 'unreadCount'));
    }
}
