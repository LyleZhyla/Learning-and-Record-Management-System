<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $attendanceTotal = AttendanceRecord::count();

        return view('coordinator.dashboard', [
            'studentCount' => User::where('role', 'student')->where('status', 'active')->count(),
            'facilitatorCount' => User::where('role', 'facilitator')->where('status', 'active')->count(),
            'sectionCount' => NstpSection::where('status', 'active')->count(),
            'attendanceRate' => $attendanceTotal ? round((AttendanceRecord::whereIn('status', ['present', 'late'])->count() / $attendanceTotal) * 100, 1) : 0,
            'gradedCount' => AssessmentSubmission::whereNotNull('score')->count(),
            'components' => NstpComponent::withCount(['sections', 'enrollments'])->orderBy('code')->get(),
            'recentSessions' => AttendanceSession::with(['section.component', 'creator'])->withCount('records')->latest('starts_at')->limit(5)->get(),
        ]);
    }
}
