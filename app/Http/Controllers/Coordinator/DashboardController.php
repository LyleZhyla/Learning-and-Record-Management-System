<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $componentId = $request->user()->nstp_component_id ?? 0;
        $component = NstpComponent::withCount(['sections', 'enrollments'])->find($componentId);
        $attendanceScope = fn ($query) => $query->whereHas('attendanceSession.section', fn ($section) => $section->where('component_id', $componentId));
        $attendanceCounts = AttendanceRecord::query()
            ->where($attendanceScope)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $attendanceTotal = (int) $attendanceCounts->sum();
        $presentCount = (int) ($attendanceCounts['present'] ?? 0);
        $lateCount = (int) ($attendanceCounts['late'] ?? 0);
        $absentCount = (int) ($attendanceCounts['absent'] ?? 0);
        $attendanceRate = $attendanceTotal ? round((($presentCount + $lateCount) / $attendanceTotal) * 100, 1) : 0;
        $sections = NstpSection::query()
            ->with('facilitator')
            ->withCount(['enrollments' => fn ($query) => $query->where('status', 'enrolled')])
            ->where('component_id', $componentId)
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
        $submissionScope = fn ($query) => $query->whereHas('assessment.section', fn ($section) => $section->where('component_id', $componentId));
        $submissionTotal = AssessmentSubmission::where($submissionScope)->count();
        $gradedCount = AssessmentSubmission::where($submissionScope)->whereNotNull('score')->count();
        $pendingGradingCount = max(0, $submissionTotal - $gradedCount);

        return view('coordinator.dashboard', [
            'studentCount' => NstpEnrollment::where('component_id', $componentId)->where('status', 'enrolled')->distinct()->count('student_id'),
            'facilitatorCount' => $sections->whereNotNull('facilitator_id')->pluck('facilitator_id')->unique()->count(),
            'sectionCount' => $sections->count(),
            'attendanceRate' => $attendanceRate,
            'attendanceTotal' => $attendanceTotal,
            'attendanceBreakdown' => [
                'present' => $presentCount,
                'late' => $lateCount,
                'absent' => $absentCount,
                'present_percentage' => $attendanceTotal ? ($presentCount / $attendanceTotal) * 100 : 0,
                'late_percentage' => $attendanceTotal ? ($lateCount / $attendanceTotal) * 100 : 0,
            ],
            'gradedCount' => $gradedCount,
            'submissionTotal' => $submissionTotal,
            'pendingGradingCount' => $pendingGradingCount,
            'gradingRate' => $submissionTotal ? round(($gradedCount / $submissionTotal) * 100, 1) : 0,
            'sections' => $sections,
            'largestSectionEnrollment' => max(1, (int) $sections->max('enrollments_count')),
            'unsectionedStudentCount' => NstpEnrollment::where('component_id', $componentId)
                ->where('status', 'enrolled')
                ->whereNull('section_id')
                ->distinct()
                ->count('student_id'),
            'sectionsWithoutFacilitator' => $sections->whereNull('facilitator_id')->count(),
            'openSessionCount' => AttendanceSession::where('status', 'open')
                ->whereHas('section', fn ($section) => $section->where('component_id', $componentId))
                ->count(),
            'assignedComponent' => $component,
            'recentSessions' => AttendanceSession::with(['section.component', 'creator'])->withCount('records')->whereHas('section', fn ($section) => $section->where('component_id', $componentId))->latest('starts_at')->limit(5)->get(),
        ]);
    }
}
