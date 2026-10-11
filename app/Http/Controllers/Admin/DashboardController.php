<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        [$academicYear, $semester] = $this->currentTerm();
        $accountCounts = User::query()
            ->selectRaw('role, status, COUNT(*) as total')
            ->groupBy('role', 'status')
            ->get()
            ->groupBy('role');
        $accountAccessOverview = collect(User::ROLE_LABELS)->map(function (string $label, string $role) use ($accountCounts): array {
            $counts = $accountCounts->get($role, collect())->pluck('total', 'status');
            $active = (int) ($counts['active'] ?? 0);
            $inactive = (int) ($counts['inactive'] ?? 0);
            $total = $active + $inactive;

            return [
                'role' => $role,
                'label' => $label,
                'active' => $active,
                'inactive' => $inactive,
                'total' => $total,
                'active_percentage' => $total > 0 ? ($active / $total) * 100 : 0,
                'inactive_percentage' => $total > 0 ? ($inactive / $total) * 100 : 0,
            ];
        })->values();
        $attendanceTrend = AttendanceRecord::query()
            ->select(['id', 'attendance_session_id', 'status'])
            ->with('attendanceSession:id,section_id,starts_at')
            ->whereHas('attendanceSession.section', fn ($section) => $section
                ->where('academic_year', $academicYear)
                ->where('semester', $semester))
            ->get()
            ->filter(fn (AttendanceRecord $record): bool => $record->attendanceSession?->starts_at !== null)
            ->groupBy(fn (AttendanceRecord $record): string => $record->attendanceSession->starts_at->toDateString())
            ->sortKeys()
            ->take(-12)
            ->map(function (Collection $records): array {
                $attended = $records->whereIn('status', ['present', 'late'])->count();
                $total = $records->count();

                return [
                    'label' => $records->first()->attendanceSession->starts_at->format('M j'),
                    'rate' => $total > 0 ? round(($attended / $total) * 100, 1) : 0,
                    'attended' => $attended,
                    'total' => $total,
                ];
            })
            ->values();

        return view('admin.dashboard', [
            'studentCount' => User::where('role', 'student')->count(),
            'facilitatorCount' => User::where('role', 'facilitator')->count(),
            'activeSectionCount' => NstpSection::where('status', 'active')->count(),
            'unassignedStudentCount' => User::query()
                ->where('role', 'student')
                ->where('status', 'active')
                ->whereDoesntHave('nstpEnrollments', fn ($query) => $query
                    ->where('academic_year', $academicYear)
                    ->where('semester', $semester))
                ->count(),
            'accountAccessOverview' => $accountAccessOverview,
            'activeAccountCount' => (int) $accountAccessOverview->sum('active'),
            'inactiveAccountCount' => (int) $accountAccessOverview->sum('inactive'),
            'attendanceChart' => $this->attendanceChart($attendanceTrend),
            'academicTerm' => (NstpSection::SEMESTERS[$semester] ?? str($semester)->headline()).' '.$academicYear,
        ]);
    }

    private function attendanceChart(Collection $trend): array
    {
        $left = 52;
        $right = 700;
        $top = 22;
        $bottom = 210;
        $points = $trend->values()->map(function (array $point, int $index) use ($trend, $left, $right, $top, $bottom): array {
            $x = $trend->count() === 1
                ? ($left + $right) / 2
                : $left + (($right - $left) * ($index / ($trend->count() - 1)));
            $y = $bottom - (($bottom - $top) * ($point['rate'] / 100));

            return $point + ['x' => round($x, 2), 'y' => round($y, 2)];
        });
        $pointString = $points->map(fn (array $point): string => $point['x'].','.$point['y'])->implode(' ');

        return [
            'width' => 720,
            'height' => 260,
            'left' => $left,
            'right' => $right,
            'points' => $points,
            'point_string' => $pointString,
            'area_points' => $points->isEmpty()
                ? ''
                : $points->first()['x'].','.$bottom.' '.$pointString.' '.$points->last()['x'].','.$bottom,
            'average_rate' => $trend->isEmpty() ? 0 : round($trend->avg('rate'), 1),
            'ticks' => collect([100, 75, 50, 25, 0])->map(fn (int $value): array => [
                'value' => $value,
                'y' => $bottom - (($bottom - $top) * ($value / 100)),
            ]),
        ];
    }

    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
