<?php

namespace App\Http\Controllers;

use App\Models\NstpSection;
use App\Models\User;
use App\Services\ActivityCalendarService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalActivityCalendarController extends Controller
{
    public function __invoke(Request $request, ActivityCalendarService $activityCalendars): View
    {
        $scope = $this->scopeFor($request->user());

        return view('activity-calendar.index', [
            ...$scope,
            'activityCalendar' => $activityCalendars->forSections(
                $scope['sectionIds'],
                $scope['routePrefix'],
                $request->string('month')->toString(),
            ),
        ]);
    }

    private function scopeFor(User $user): array
    {
        return match ($user->role) {
            'facilitator' => [
                'layout' => 'layouts.facilitator',
                'routePrefix' => 'facilitator',
                'sectionIds' => $user->facilitatedSections()->pluck('id'),
                'eyebrow' => 'Facilitator schedule',
                'description' => 'Assessment deadlines, project activities, and attendance sessions for your assigned sections.',
            ],
            'coordinator' => [
                'layout' => 'layouts.coordinator',
                'routePrefix' => 'coordinator',
                'sectionIds' => NstpSection::query()
                    ->where('component_id', $user->nstp_component_id ?? 0)
                    ->where('status', 'active')
                    ->pluck('id'),
                'eyebrow' => 'Component-wide schedule',
                'description' => 'Assessment deadlines, project activities, and attendance sessions across your assigned component.',
            ],
            'nstp_admin' => $this->nstpAdminScope(),
            default => abort(403),
        };
    }

    private function nstpAdminScope(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;
        $academicYear = $start.'-'.($start + 1);
        $semester = now()->month >= 6 ? 'first' : 'second';

        return [
            'layout' => 'layouts.nstp-admin',
            'routePrefix' => 'nstp_admin',
            'sectionIds' => NstpSection::query()
                ->where('academic_year', $academicYear)
                ->where('semester', $semester)
                ->where('status', 'active')
                ->pluck('id'),
            'eyebrow' => 'NSTP-wide schedule',
            'description' => 'Assessment deadlines, project activities, and attendance sessions across all active NSTP sections this term.',
        ];
    }
}
