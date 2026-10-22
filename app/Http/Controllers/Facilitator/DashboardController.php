<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CommunityProjectActivity;
use App\Models\NstpEnrollment;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $sections = $request->user()->facilitatedSections()
            ->with(['component', 'schedule'])
            ->withCount(['enrollments' => fn ($query) => $query->where('status', 'enrolled')])
            ->orderBy('name')
            ->get();
        $sectionIds = $sections->pluck('id');
        $attendanceCounts = AttendanceRecord::query()
            ->whereHas('attendanceSession', fn ($query) => $query->whereIn('section_id', $sectionIds))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $attendanceTotal = (int) $attendanceCounts->sum();
        $presentCount = (int) ($attendanceCounts['present'] ?? 0);
        $lateCount = (int) ($attendanceCounts['late'] ?? 0);
        $absentCount = (int) ($attendanceCounts['absent'] ?? 0);
        $attendanceRate = $attendanceTotal ? round((($presentCount + $lateCount) / $attendanceTotal) * 100, 1) : 0;
        $submissionQuery = AssessmentSubmission::query()
            ->whereHas('assessment', fn ($query) => $query->whereIn('section_id', $sectionIds));
        $submissionTotal = (clone $submissionQuery)->count();
        $gradedCount = (clone $submissionQuery)->whereNotNull('score')->count();
        $pendingGradingCount = max(0, $submissionTotal - $gradedCount);
        $stats = [
            'sections' => $sections->count(),
            'students' => NstpEnrollment::whereIn('section_id', $sectionIds)->where('status', 'enrolled')->distinct()->count('student_id'),
            'sessions' => AttendanceSession::whereIn('section_id', $sectionIds)->count(),
            'ungraded' => $pendingGradingCount,
        ];

        $calendarMonth = $this->calendarMonth($request->string('month')->toString());
        $calendarGridStart = $calendarMonth->startOfWeek();
        $calendarGridEnd = $calendarMonth->endOfMonth()->endOfWeek();
        $calendarRangeStart = $calendarGridStart->startOfDay();
        $calendarRangeEnd = $calendarGridEnd->endOfDay();

        $assessmentEvents = Assessment::query()
            ->with('section.component')
            ->whereIn('section_id', $sectionIds)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$calendarRangeStart, $calendarRangeEnd])
            ->get()
            ->map(fn (Assessment $assessment): array => [
                'date' => $assessment->due_at->toDateString(),
                'time' => $assessment->due_at->format('g:i A'),
                'title' => $assessment->title,
                'category' => 'Assessment due',
                'type' => 'assessment',
                'meta' => $assessment->section->name.' · '.$assessment->section->component->code,
                'url' => route('facilitator.assessments.show', $assessment),
            ]);

        $projectActivityEvents = CommunityProjectActivity::query()
            ->with('project.section.component')
            ->whereHas('project', fn ($query) => $query->whereIn('section_id', $sectionIds))
            ->whereNotNull('scheduled_date')
            ->whereBetween('scheduled_date', [$calendarGridStart->toDateString(), $calendarGridEnd->toDateString()])
            ->get()
            ->map(fn (CommunityProjectActivity $activity): array => [
                'date' => $activity->scheduled_date->toDateString(),
                'time' => null,
                'title' => $activity->title,
                'category' => 'Project activity',
                'type' => 'project',
                'meta' => $activity->project->title.' · '.($activity->project->section?->name ?? 'No section'),
                'url' => route('facilitator.community-projects.show', $activity->project).'#project-activities',
            ]);

        $attendanceEvents = AttendanceSession::query()
            ->with('section.component')
            ->whereIn('section_id', $sectionIds)
            ->whereBetween('starts_at', [$calendarRangeStart, $calendarRangeEnd])
            ->get()
            ->map(fn (AttendanceSession $session): array => [
                'date' => $session->starts_at->toDateString(),
                'time' => $session->starts_at->format('g:i A'),
                'title' => $session->title,
                'category' => 'Attendance session',
                'type' => 'attendance',
                'meta' => $session->section->name.' · '.$session->section->component->code,
                'url' => route('facilitator.attendance.index'),
            ]);

        $calendarEvents = $assessmentEvents
            ->concat($projectActivityEvents)
            ->concat($attendanceEvents)
            ->sortBy(fn (array $event): string => $event['date'].' '.($event['time'] ?? '00:00'))
            ->values();
        $calendarEventsByDate = $calendarEvents->groupBy('date');
        $calendarDays = collect();
        for ($day = $calendarGridStart; $day->lte($calendarGridEnd); $day = $day->addDay()) {
            $calendarDays->push($day);
        }
        $calendarMonthEvents = $calendarEvents
            ->filter(fn (array $event): bool => str_starts_with($event['date'], $calendarMonth->format('Y-m')))
            ->values();

        return view('facilitator.dashboard', [
            'sections' => $sections,
            'stats' => $stats,
            'attendanceRate' => $attendanceRate,
            'attendanceTotal' => $attendanceTotal,
            'attendanceBreakdown' => [
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'present_percentage' => $attendanceTotal ? ($presentCount / $attendanceTotal) * 100 : 0,
                'late_percentage' => $attendanceTotal ? ($lateCount / $attendanceTotal) * 100 : 0,
            ],
            'submissionTotal' => $submissionTotal,
            'gradedCount' => $gradedCount,
            'pendingGradingCount' => $pendingGradingCount,
            'gradingRate' => $submissionTotal ? round(($gradedCount / $submissionTotal) * 100, 1) : 0,
            'largestSectionEnrollment' => max(1, (int) $sections->max('enrollments_count')),
            'openSessionCount' => AttendanceSession::whereIn('section_id', $sectionIds)->where('status', 'open')->count(),
            'recentSessions' => AttendanceSession::query()
                ->with('section.component')
                ->withCount('records')
                ->whereIn('section_id', $sectionIds)
                ->latest('starts_at')
                ->limit(5)
                ->get(),
            'calendarMonth' => $calendarMonth,
            'calendarDays' => $calendarDays,
            'calendarEventsByDate' => $calendarEventsByDate,
            'calendarMonthEvents' => $calendarMonthEvents,
        ]);
    }

    private function calendarMonth(string $requestedMonth): CarbonImmutable
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
