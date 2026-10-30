<?php

namespace App\Services;

use App\Models\DataPrivacyRequest;
use App\Models\PrivacyIncident;
use App\Models\User;
use Illuminate\Support\Collection;

class AnonymizedAnalyticsService
{
    private const MINIMUM_GROUP_SIZE = 5;

    public function summary(): array
    {
        return [
            'users_by_role' => $this->suppress(User::query()->selectRaw('role as label, COUNT(*) as total')->groupBy('role')->pluck('total', 'label')),
            'requests_by_type' => $this->suppress(DataPrivacyRequest::query()->selectRaw('request_type as label, COUNT(*) as total')->groupBy('request_type')->pluck('total', 'label')),
            'incidents_by_severity' => $this->suppress(PrivacyIncident::query()->selectRaw('severity as label, COUNT(*) as total')->groupBy('severity')->pluck('total', 'label')),
            'minimum_group_size' => self::MINIMUM_GROUP_SIZE,
        ];
    }

    private function suppress(Collection $groups): Collection
    {
        return $groups->map(fn (int|string $total): int|string => (int) $total < self::MINIMUM_GROUP_SIZE ? '<'.self::MINIMUM_GROUP_SIZE : (int) $total);
    }
}
