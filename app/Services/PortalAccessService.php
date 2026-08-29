<?php

namespace App\Services;

use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PortalAccessService
{
    public function routePrefix(User $user): string
    {
        return match ($user->role) {
            'super_admin' => 'admin',
            'nstp_admin' => 'nstp_admin',
            'coordinator' => 'coordinator',
            'facilitator' => 'facilitator',
            'student' => 'student',
            default => abort(403),
        };
    }

    public function layout(User $user): string
    {
        return match ($user->role) {
            'super_admin' => 'layouts.admin',
            'nstp_admin' => 'layouts.nstp-admin',
            'coordinator' => 'layouts.coordinator',
            'facilitator' => 'layouts.facilitator',
            'student' => 'layouts.student',
            default => abort(403),
        };
    }

    public function manageableSections(User $user): Builder
    {
        $query = NstpSection::query();

        if ($user->isFacilitator()) {
            $query->where('facilitator_id', $user->id);
        } elseif (! $user->isSuperAdmin() && ! $user->isNstpAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function ensureCanManageSection(User $user, NstpSection $section): void
    {
        abort_unless(
            $user->isSuperAdmin()
            || $user->isNstpAdmin()
            || ($user->isFacilitator() && $section->facilitator_id === $user->id),
            403,
        );
    }

    public function ensureCanScanSection(User $user, NstpSection $section): void
    {
        abort_unless(
            $user->isNstpAdmin()
            || $user->isCoordinator()
            || ($user->isFacilitator() && $section->facilitator_id === $user->id),
            403,
        );
    }

    public function currentEnrollment(User $student): ?NstpEnrollment
    {
        return NstpEnrollment::with(['component', 'section'])
            ->where('student_id', $student->id)
            ->latest('academic_year')
            ->latest('semester')
            ->first();
    }
}
