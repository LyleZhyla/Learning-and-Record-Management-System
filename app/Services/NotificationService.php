<?php

namespace App\Services;

use App\Models\Announcement;
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
            'facilitator' => $user->facilitatedSections()->pluck('component_id')->unique()->values()->all(),
            'coordinator' => array_values(array_filter([$user->nstp_component_id])),
            default => [],
        };

        return $query
            ->whereIn('audience', ['all', $audience])
            ->where(fn ($items) => $items->whereNull('component_id')->orWhereIn('component_id', $componentIds));
    }
}
