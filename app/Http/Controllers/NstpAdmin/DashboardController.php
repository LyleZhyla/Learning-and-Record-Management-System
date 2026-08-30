<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        [$academicYear, $semester] = $this->currentTerm();

        return view('nstp_admin.dashboard', [
            'studentCount' => User::where('role', 'student')->where('status', 'active')->count(),
            'facilitatorCount' => User::where('role', 'facilitator')->where('status', 'active')->count(),
            'coordinatorCount' => User::where('role', 'coordinator')->where('status', 'active')->count(),
            'unassignedStudentCount' => User::query()
                ->where('role', 'student')
                ->where('status', 'active')
                ->whereDoesntHave('nstpEnrollments', fn ($query) => $query
                    ->where('academic_year', $academicYear)
                    ->where('semester', $semester))
                ->count(),
            'recentAccounts' => User::whereIn('role', ['student', 'facilitator', 'coordinator'])
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
