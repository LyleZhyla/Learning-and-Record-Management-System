<?php

namespace App\View\Components;

use App\Models\ChatMessage;
use App\Models\ChatGroupMessage;
use App\Models\StudentNotification;
use App\Services\NotificationService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Component;

class NotificationBell extends Component
{
    public function __construct() {}

    public function render(): View|Closure|string
    {
        $user = auth()->user();
        $query = app(NotificationService::class)->visibleQuery($user);
        $unreadAnnouncements = (clone $query)
            ->whereDoesntHave('readers', fn ($readers) => $readers->whereKey($user->id));
        $unreadAnnouncementCount = (clone $unreadAnnouncements)->count();
        $notifications = $unreadAnnouncements->with(['author', 'component'])
            ->withExists(['readers as is_read' => fn ($readers) => $readers->whereKey($user->id)])
            ->latest('published_at')->limit(6)->get();

        $messageRoutePrefix = match ($user->role) {
            'super_admin' => 'admin',
            'nstp_admin' => 'nstp_admin',
            'coordinator', 'facilitator', 'student' => $user->role,
            default => null,
        };
        $unreadMessageCount = 0;
        $messageNotifications = collect();
        $groupMessageNotifications = collect();
        $eventNotificationQuery = StudentNotification::where('user_id', $user->id)->whereNull('read_at');
        $unreadEventNotificationCount = (clone $eventNotificationQuery)->count();
        $eventNotifications = $eventNotificationQuery->latest()->limit(8)->get();

        if ($messageRoutePrefix) {
            $unreadMessages = ChatMessage::query()
                ->where('recipient_id', $user->id)
                ->whereNull('read_at')
                ->whereHas('sender', fn ($sender) => $sender->where('status', 'active'));
            $unreadMessageCount = (clone $unreadMessages)->count();
            $groups = (clone $unreadMessages)
                ->select('sender_id', DB::raw('MAX(id) as latest_id'), DB::raw('COUNT(*) as unread_from_sender'))
                ->groupBy('sender_id')
                ->orderByDesc('latest_id')
                ->limit(6)
                ->get();
            $latestMessages = ChatMessage::with(['sender', 'section.component'])
                ->whereIn('id', $groups->pluck('latest_id'))
                ->get()
                ->keyBy('id');
            $messageNotifications = $groups->map(function ($group) use ($latestMessages) {
                $message = $latestMessages->get((int) $group->latest_id);

                return $message?->setAttribute('unread_from_sender', (int) $group->unread_from_sender);
            })->filter()->values();

            if ($user->isFacilitator() || $user->isStudent()) {
                $unreadGroupMessages = ChatGroupMessage::unreadFor($user);
                $unreadMessageCount += (clone $unreadGroupMessages)->count();
                $groupMessageSummaries = (clone $unreadGroupMessages)
                    ->select('chat_group_messages.chat_group_id')
                    ->selectRaw('MAX(chat_group_messages.id) as latest_id, COUNT(*) as unread_from_group')
                    ->groupBy('chat_group_messages.chat_group_id')
                    ->orderByDesc('latest_id')
                    ->limit(6)
                    ->get();
                $latestGroupMessages = ChatGroupMessage::with(['sender', 'group.section'])
                    ->whereIn('id', $groupMessageSummaries->pluck('latest_id'))
                    ->get()
                    ->keyBy('id');
                $groupMessageNotifications = $groupMessageSummaries->map(function ($summary) use ($latestGroupMessages) {
                    return $latestGroupMessages->get((int) $summary->latest_id)
                        ?->setAttribute('unread_from_group', (int) $summary->unread_from_group);
                })->filter()->values();
            }
        }

        $unreadCount = $unreadAnnouncementCount + $unreadMessageCount + $unreadEventNotificationCount;

        return view('components.notification-bell', compact(
            'notifications',
            'messageNotifications',
            'groupMessageNotifications',
            'eventNotifications',
            'messageRoutePrefix',
            'unreadCount',
        ));
    }
}
