<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\LearningMaterial;
use App\Models\NstpEnrollment;
use App\Services\GradeService;
use App\Services\PortalAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PortalAccessService $access, GradeService $grades): View
    {
        $student = $request->user();
        $enrollment = $access->currentEnrollment($student);
        $enrollment?->loadMissing(['section.facilitator', 'section.schedule']);
        $pendingEnrollment = $enrollment ? null : NstpEnrollment::with('component')
            ->where('student_id', $student->id)
            ->where('status', 'pending_approval')
            ->latest('academic_year')
            ->latest('semester')
            ->first();
        $stats = [
            'attendance_completed' => 0,
            'attendance_total' => 0,
            'attendance_remaining' => 0,
            'materials' => 0,
            'assessments_completed' => 0,
            'assessments_total' => 0,
            'assessments_remaining' => 0,
            'grade' => null,
        ];
        $attendanceBreakdown = ['present' => 0, 'late' => 0, 'absent' => 0];
        $upcomingAssessments = collect();
        $recentAttendance = collect();

        if ($enrollment) {
            $attendanceSessionIds = AttendanceSession::query()
                ->where('section_id', $enrollment->section_id)
                ->where('starts_at', '<=', now())
                ->pluck('id');
            $stats['attendance_total'] = $attendanceSessionIds->count();
            $attendanceCounts = AttendanceRecord::query()
                ->where('student_id', $student->id)
                ->whereIn('attendance_session_id', $attendanceSessionIds)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
            $attendanceBreakdown = [
                'present' => (int) ($attendanceCounts['present'] ?? 0),
                'late' => (int) ($attendanceCounts['late'] ?? 0),
                'absent' => (int) ($attendanceCounts['absent'] ?? 0),
            ];
            $stats['attendance_completed'] = $attendanceBreakdown['present'] + $attendanceBreakdown['late'];
            $stats['attendance_remaining'] = max(0, $stats['attendance_total'] - $stats['attendance_completed']);
            $stats['materials'] = LearningMaterial::where('status', 'published')->where('component_id', $enrollment->component_id)
                ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id))->count();
            $assessmentIds = Assessment::where('section_id', $enrollment->section_id)->where('status', 'published')->pluck('id');
            $submitted = AssessmentSubmission::where('student_id', $student->id)
                ->whereIn('assessment_id', $assessmentIds)
                ->distinct()
                ->pluck('assessment_id');
            $stats['assessments_total'] = $assessmentIds->count();
            $stats['assessments_completed'] = $submitted->count();
            $stats['assessments_remaining'] = max(0, $stats['assessments_total'] - $stats['assessments_completed']);
            if ($enrollment->section_id) {
                $stats['grade'] = $grades->summary($student, $enrollment->section_id)['grade'];
            }

            $upcomingAssessments = Assessment::query()
                ->where('section_id', $enrollment->section_id)
                ->where('status', 'published')
                ->whereNotIn('id', $submitted)
                ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_at')
                ->limit(4)
                ->get();
            $recentAttendance = AttendanceRecord::query()
                ->with('attendanceSession')
                ->where('student_id', $student->id)
                ->whereIn('attendance_session_id', $attendanceSessionIds)
                ->whereIn('status', ['present', 'late', 'absent'])
                ->latest('checked_in_at')
                ->latest('id')
                ->limit(4)
                ->get();
        }

        return view('student.dashboard', [
            'enrollment' => $enrollment,
            'pendingEnrollment' => $pendingEnrollment,
            'stats' => $stats,
            'attendanceBreakdown' => $attendanceBreakdown,
            'attendanceRate' => $stats['attendance_total']
                ? round(($stats['attendance_completed'] / $stats['attendance_total']) * 100, 1)
                : 0,
            'assessmentRate' => $stats['assessments_total']
                ? round(($stats['assessments_completed'] / $stats['assessments_total']) * 100, 1)
                : 0,
            'upcomingAssessments' => $upcomingAssessments,
            'recentAttendance' => $recentAttendance,
        ]);
    }
}
