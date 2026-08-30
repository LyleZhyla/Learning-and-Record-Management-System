<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $enrolleeCounts = NstpEnrollment::query()
            ->where('status', 'enrolled')
            ->selectRaw('component_id, COUNT(DISTINCT student_id) as total')
            ->groupBy('component_id')
            ->pluck('total', 'component_id');

        $componentEnrollments = NstpComponent::query()
            ->orderBy('code')
            ->get()
            ->map(fn (NstpComponent $component) => [
                'code' => $component->code,
                'name' => $component->name,
                'count' => (int) ($enrolleeCounts[$component->id] ?? 0),
            ]);

        return view('admin.dashboard', [
            'studentCount' => User::where('role', 'student')->count(),
            'facilitatorCount' => User::where('role', 'facilitator')->count(),
            'activeSectionCount' => NstpSection::where('status', 'active')->count(),
            'componentEnrollments' => $componentEnrollments,
        ]);
    }
}
