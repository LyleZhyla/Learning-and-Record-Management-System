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
        $attendanceTotal = AttendanceRecord::where($attendanceScope)->count();

        return view('coordinator.dashboard', [
            'studentCount' => NstpEnrollment::where('component_id', $componentId)->distinct()->count('student_id'),
            'facilitatorCount' => NstpSection::where('component_id', $componentId)->whereNotNull('facilitator_id')->distinct('facilitator_id')->count('facilitator_id'),
            'sectionCount' => NstpSection::where('component_id', $componentId)->where('status', 'active')->count(),
            'attendanceRate' => $attendanceTotal ? round((AttendanceRecord::where($attendanceScope)->whereIn('status', ['present', 'late'])->count() / $attendanceTotal) * 100, 1) : 0,
            'gradedCount' => AssessmentSubmission::whereNotNull('score')->whereHas('assessment.section', fn ($section) => $section->where('component_id', $componentId))->count(),
            'component' => $component,
            'components' => collect([$component])->filter(),
            'recentSessions' => AttendanceSession::with(['section.component', 'creator'])->withCount('records')->whereHas('section', fn ($section) => $section->where('component_id', $componentId))->latest('starts_at')->limit(5)->get(),
        ]);
    }
}
