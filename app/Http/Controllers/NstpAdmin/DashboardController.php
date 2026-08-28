<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('nstp_admin.dashboard', [
            'studentCount' => User::where('role', 'student')->where('status', 'active')->count(),
            'facilitatorCount' => User::where('role', 'facilitator')->where('status', 'active')->count(),
            'coordinatorCount' => User::where('role', 'coordinator')->where('status', 'active')->count(),
            'activeUserCount' => User::where('status', 'active')->count(),
            'recentAccounts' => User::whereIn('role', ['student', 'facilitator', 'coordinator'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }
}
