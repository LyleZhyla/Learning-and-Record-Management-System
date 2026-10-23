<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpEnrollment;
use App\Services\ActivityCalendarService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ActivityCalendarService $activityCalendars): View
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

        $activityCalendar = $activityCalendars->forSections(
            $sectionIds,
            'facilitator',
            $request->string('month')->toString(),
        );

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
            'activityCalendar' => $activityCalendar,
        ]);
    }
}
