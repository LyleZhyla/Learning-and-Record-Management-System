<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Services\GradeService;
use App\Services\PortalAccessService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(
        private PortalAccessService $access,
        private GradeService $grades,
    ) {}

    public function __invoke(Request $request): View
    {
        return view('student.reports.index', $this->reportData($request));
    }

    public function download(Request $request, string $type): Response
    {
        $request->merge(['download_type' => $type]);
        $request->validate(['download_type' => ['required', Rule::in(['grades', 'attendance', 'assessments', 'certificate'])]]);
        $data = $this->reportData($request);
        $student = $request->user();

        if ($type === 'certificate') {
            $summary = $data['gradeSummary'];
            $eligible = $data['enrollment']
                && $summary
                && $summary['total_count'] > 0
                && $summary['graded_count'] === $summary['total_count']
                && $summary['percentage'] !== null
                && $summary['percentage'] >= (float) $summary['settings']->passing_percentage;
            abort_unless($eligible, 422, 'The completion certificate becomes available after all graded requirements are complete and passing.');
        }

        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($type === 'certificate' ? 'student.reports.certificate' : 'student.reports.pdf', $data + [
            'downloadType' => $type,
            'student' => $student,
        ])->render());
        $dompdf->setPaper($type === 'certificate' ? 'a4' : 'a4', $type === 'certificate' ? 'landscape' : 'portrait');
        $dompdf->render();
        $filename = str($student->name.' '.$type)->slug().'-'.now()->format('Y-m-d').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function reportData(Request $request): array
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
        $certificateEligible = $enrollment
            && $gradeSummary
            && $gradeSummary['total_count'] > 0
            && $gradeSummary['graded_count'] === $gradeSummary['total_count']
            && $gradeSummary['percentage'] !== null
            && $gradeSummary['percentage'] >= (float) $gradeSummary['settings']->passing_percentage;

        return [
            'enrollment' => $enrollment,
            'attendanceRecords' => $attendanceRecords,
            'submissions' => $submissions,
            'gradeSummary' => $gradeSummary,
            'certificateEligible' => $certificateEligible,
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
        ];
    }
}
