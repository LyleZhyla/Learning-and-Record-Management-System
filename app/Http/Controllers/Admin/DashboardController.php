<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\NstpSection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'studentCount' => User::where('role', 'student')->count(),
            'facilitatorCount' => User::where('role', 'facilitator')->count(),
            'activeSectionCount' => NstpSection::where('status', 'active')->count(),
        ]);
    }
}
