<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\NotificationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NotificationService
{
    public function visibleQuery(User $user): Builder
    {
        $query = Announcement::query()
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->where(fn ($items) => $items->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        if ($user->isSuperAdmin() || $user->isNstpAdmin()) {
            return $query;
        }

        $audience = match ($user->role) {
            'student' => 'students',
            'facilitator' => 'facilitators',
            'coordinator' => 'coordinators',
            default => 'all',
        };
        $componentIds = match ($user->role) {
            'student' => $user->nstpEnrollments()->pluck('component_id')->unique()->values()->all(),
            'facilitator' => $user->facilitatedSections()->pluck('component_id')->push($user->nstp_component_id)->filter()->unique()->values()->all(),
            'coordinator' => array_values(array_filter([$user->nstp_component_id])),
            default => [],
        };

        return $query
            ->whereIn('audience', ['all', $audience])
            ->where(fn ($items) => $items->whereNull('component_id')->orWhereIn('component_id', $componentIds));
    }

    public function notificationQuery(User $user, string $channel): Builder
    {
        $query = $this->visibleQuery($user);
        $rule = NotificationRule::configured('announcement');
        if (! $rule->usesChannel($channel)) {
            return $query->whereRaw('1 = 0');
        }

        if ($rule->schedule_mode === 'delayed') {
            $query->where('published_at', '<=', now()->subMinutes(max(1, $rule->delay_minutes)));
        }

        return $query;
    }

    public function presentAnnouncement(Announcement $announcement): Announcement
    {
        $rule = NotificationRule::configured('announcement');
        $values = [
            'announcement_title' => $announcement->title,
            'announcement_body' => $announcement->body,
            'author_name' => $announcement->author?->name,
        ];

        return $announcement
            ->setAttribute('notification_title', $rule->renderedTitle($values))
            ->setAttribute('notification_body', $rule->renderedBody($values));
    }
}
