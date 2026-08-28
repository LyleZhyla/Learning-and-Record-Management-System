<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceSession;
use App\Models\LearningMaterial;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $sections = $request->user()->facilitatedSections()->with('component')->withCount('enrollments')->get();
        $sectionIds = $sections->pluck('id');
        $stats = [
            'sections' => $sections->count(),
            'students' => $sections->sum('enrollments_count'),
            'sessions' => AttendanceSession::whereIn('section_id', $sectionIds)->count(),
            'ungraded' => AssessmentSubmission::whereNull('score')->whereHas('assessment', fn ($q) => $q->whereIn('section_id', $sectionIds))->count(),
        ];

        return view('facilitator.dashboard', compact('sections', 'stats'));
    }
}
