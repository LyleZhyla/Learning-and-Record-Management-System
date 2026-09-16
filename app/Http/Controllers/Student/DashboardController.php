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
        $enrollment = $access->currentEnrollment($request->user());
        $pendingEnrollment = $enrollment ? null : NstpEnrollment::with('component')
            ->where('student_id', $request->user()->id)
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
        if ($enrollment) {
            $attendanceSessionIds = AttendanceSession::query()
                ->where('section_id', $enrollment->section_id)
                ->where('starts_at', '<=', now())
                ->pluck('id');
            $stats['attendance_total'] = $attendanceSessionIds->count();
            $stats['attendance_completed'] = AttendanceRecord::query()
                ->where('student_id', $request->user()->id)
                ->whereIn('attendance_session_id', $attendanceSessionIds)
                ->whereIn('status', ['present', 'late'])
                ->count();
            $stats['attendance_remaining'] = max(0, $stats['attendance_total'] - $stats['attendance_completed']);
            $stats['materials'] = LearningMaterial::where('status', 'published')->where('component_id', $enrollment->component_id)
                ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id))->count();
            $assessmentIds = Assessment::where('section_id', $enrollment->section_id)->where('status', 'published')->pluck('id');
            $submitted = AssessmentSubmission::where('student_id', $request->user()->id)
                ->whereIn('assessment_id', $assessmentIds)
                ->distinct()
                ->pluck('assessment_id');
            $stats['assessments_total'] = $assessmentIds->count();
            $stats['assessments_completed'] = $submitted->count();
            $stats['assessments_remaining'] = max(0, $stats['assessments_total'] - $stats['assessments_completed']);
            if ($enrollment->section_id) {
                $stats['grade'] = $grades->summary($request->user(), $enrollment->section_id)['grade'];
            }
        }

        return view('student.dashboard', compact('enrollment', 'pendingEnrollment', 'stats'));
    }
}
