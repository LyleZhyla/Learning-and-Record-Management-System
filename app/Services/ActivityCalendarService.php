<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AttendanceSession;
use App\Models\CommunityProjectActivity;
use Carbon\CarbonImmutable;

class ActivityCalendarService
{
    public function forSections(iterable $sectionIds, string $routePrefix, string $requestedMonth = ''): array
    {
        $sectionIds = collect($sectionIds)->filter()->values();
        $month = $this->month($requestedMonth);
        $gridStart = $month->startOfWeek();
        $gridEnd = $month->endOfMonth()->endOfWeek();
        $rangeStart = $gridStart->startOfDay();
        $rangeEnd = $gridEnd->endOfDay();

        $assessmentEvents = Assessment::query()
            ->with('section.component')
            ->whereIn('section_id', $sectionIds)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$rangeStart, $rangeEnd])
            ->get()
            ->map(fn (Assessment $assessment): array => [
                'date' => $assessment->due_at->toDateString(),
                'time' => $assessment->due_at->format('g:i A'),
                'title' => $assessment->title,
                'category' => 'Assessment due',
                'type' => 'assessment',
                'meta' => $assessment->section->name.' · '.$assessment->section->component->code,
                'url' => route($routePrefix.'.assessments.show', $assessment),
            ]);

        $projectEvents = CommunityProjectActivity::query()
            ->with('project.section.component')
            ->whereHas('project', fn ($query) => $query->whereIn('section_id', $sectionIds))
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$gridStart->toDateString(), $gridEnd->toDateString()])
            ->get()
            ->map(fn (CommunityProjectActivity $activity): array => [
                'date' => $activity->scheduled_date->toDateString(),
                'time' => null,
                'title' => $activity->title,
                'category' => 'Project activity',
                'type' => 'project',
                'meta' => $activity->project->title.' · '.($activity->project->section?->name ?? 'No section'),
                'url' => route($routePrefix.'.community-projects.show', $activity->project).'#project-activities',
            ]);

        $attendanceEvents = AttendanceSession::query()
            ->with('section.component')
            ->whereIn('section_id', $sectionIds)
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->get()
            ->map(fn (AttendanceSession $session): array => [
                'date' => $session->starts_at->toDateString(),
                'time' => $session->starts_at->format('g:i A'),
                'title' => $session->title,
                'category' => 'Attendance session',
                'type' => 'attendance',
                'meta' => $session->section->name.' · '.$session->section->component->code,
                'url' => route($routePrefix.'.attendance.index'),
            ]);

        $events = $assessmentEvents
            ->concat($projectEvents)
            ->concat($attendanceEvents)
            ->sortBy(fn (array $event): string => $event['date'].' '.($event['time'] ?? '00:00'))
            ->values();
        $days = collect();
        for ($day = $gridStart; $day->lte($gridEnd); $day = $day->addDay()) {
            $days->push($day);
        }

        return [
            'month' => $month,
            'days' => $days,
            'events_by_date' => $events->groupBy('date'),
            'month_events' => $events
                ->filter(fn (array $event): bool => str_starts_with($event['date'], $month->format('Y-m')))
                ->values(),
        ];
    }

    private function month(string $requestedMonth): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $requestedMonth) === 1) {
            try {
                $month = CarbonImmutable::createFromFormat('!Y-m', $requestedMonth);

                if ($month !== false && $month->format('Y-m') === $requestedMonth) {
                    return $month->startOfMonth();
                }
            } catch (\Throwable) {
                // Fall through to the current month for invalid input.
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
