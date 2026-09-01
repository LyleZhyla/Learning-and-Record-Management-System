<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
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
        $attendedCount = $attendanceRecords->whereIn('status', ['present', 'late'])->count();

        return view('student.reports.index', [
            'enrollment' => $enrollment,
            'attendanceRecords' => $attendanceRecords,
            'submissions' => $submissions,
            'gradeSummary' => $gradeSummary,
            'metrics' => [
                'attendance_rate' => $attendanceRecords->isEmpty()
                    ? 0
                    : round(($attendedCount / $attendanceRecords->count()) * 100, 1),
                'attendance_records' => $attendanceRecords->count(),
                'submissions' => $submissions->count(),
                'graded_submissions' => $submissions->whereNotNull('score')->count(),
            ],
        ]);
    }
}
