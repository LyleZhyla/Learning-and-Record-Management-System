<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
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
        $stats = ['attendance' => 0, 'materials' => 0, 'pending' => 0, 'grade' => null];
        if ($enrollment) {
            $stats['attendance'] = AttendanceRecord::where('student_id', $request->user()->id)->whereIn('status', ['present', 'late'])->count();
            $stats['materials'] = LearningMaterial::where('status', 'published')->where('component_id', $enrollment->component_id)
                ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id))->count();
            $assessmentIds = Assessment::where('section_id', $enrollment->section_id)->where('status', 'published')->pluck('id');
            $submitted = AssessmentSubmission::where('student_id', $request->user()->id)->whereIn('assessment_id', $assessmentIds)->pluck('assessment_id');
            $stats['pending'] = $assessmentIds->diff($submitted)->count();
            if ($enrollment->section_id) {
                $stats['grade'] = $grades->summary($request->user(), $enrollment->section_id)['grade'];
            }
        }

        return view('student.dashboard', compact('enrollment', 'pendingEnrollment', 'stats'));
    }
}
