<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\GradeService;
use App\Services\PortalAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private PortalAccessService $access,
        private GradeService $grades,
    ) {}

    public function __invoke(Request $request): View
    {
        $student = $request->user();
        $enrollment = $this->access->currentEnrollment($student)?->load(['component', 'section.facilitator']);
        $attendanceRecords = AttendanceRecord::with(['attendanceSession.section.component'])
            ->where('student_id', $student->id)
            ->whereNull('archived_at')
            ->latest('checked_in_at')
            ->get();
        $submissions = AssessmentSubmission::with(['assessment.section.component'])
            ->where('student_id', $student->id)
            ->latest('submitted_at')
            ->get();
        $gradeSummary = $enrollment?->section_id
            ? $this->grades->summary($student, $enrollment->section_id)
            : null;
        $attendanceSessionIds = $enrollment?->section_id
            ? AttendanceSession::query()
                ->where('section_id', $enrollment->section_id)
                ->where('starts_at', '<=', now())
                ->pluck('id')
            : collect();
        $attendanceTotal = $attendanceSessionIds->count();
        $attendedCount = $attendanceRecords
            ->whereIn('attendance_session_id', $attendanceSessionIds)
            ->whereIn('status', ['present', 'late'])
            ->count();
        $assessmentIds = $enrollment?->section_id
            ? Assessment::query()
                ->where('section_id', $enrollment->section_id)
                ->where('status', 'published')
                ->pluck('id')
            : collect();
        $submittedCount = $submissions
            ->whereIn('assessment_id', $assessmentIds)
            ->unique('assessment_id')
            ->count();

        return view('student.reports.index', [
            'enrollment' => $enrollment,
            'attendanceRecords' => $attendanceRecords,
            'submissions' => $submissions,
            'gradeSummary' => $gradeSummary,
            'metrics' => [
                'attendance_rate' => $attendanceTotal === 0
                    ? 0
                    : round(($attendedCount / $attendanceTotal) * 100, 1),
                'attendance_completed' => $attendedCount,
                'attendance_total' => $attendanceTotal,
                'attendance_remaining' => max(0, $attendanceTotal - $attendedCount),
                'assessments_completed' => $submittedCount,
                'assessments_total' => $assessmentIds->count(),
                'assessments_remaining' => max(0, $assessmentIds->count() - $submittedCount),
                'graded_submissions' => $submissions->whereNotNull('score')->count(),
            ],
        ]);
    }
}
