<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\NstpComponent;
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
        $components = NstpComponent::query()->orderBy('code')->get();
        $selectionQuery = NstpEnrollment::query()
            ->whereIn('status', ['enrolled', 'pending_approval'])
            ->where('academic_year', $academicYear)
            ->where('semester', $semester);
        $enrolleeCounts = (clone $selectionQuery)
            ->selectRaw('component_id, COUNT(DISTINCT student_id) as total')
            ->groupBy('component_id')
            ->pluck('total', 'component_id');
        $rotc = $components->firstWhere('code', 'ROTC');
        $rotcCategoryCounts = $rotc
            ? (clone $selectionQuery)->where('component_id', $rotc->id)
                ->whereNotNull('rotc_category')
                ->selectRaw('rotc_category, COUNT(DISTINCT student_id) as total')
                ->groupBy('rotc_category')
                ->pluck('total', 'rotc_category')
            : collect();

        $componentEnrollments = $components->flatMap(function (NstpComponent $component) use ($enrolleeCounts, $rotcCategoryCounts) {
            if ($component->code !== 'ROTC') {
                return [[
                    'code' => $component->code,
                    'name' => $component->name,
                    'count' => (int) ($enrolleeCounts[$component->id] ?? 0),
                ]];
            }

            $categoryRows = collect(NstpEnrollment::ROTC_CATEGORIES)->keys()->map(fn (string $category) => [
                'code' => $category,
                'name' => 'ROTC category',
                'count' => (int) ($rotcCategoryCounts[$category] ?? 0),
            ]);
            $unspecifiedCount = max(0, (int) ($enrolleeCounts[$component->id] ?? 0) - (int) $rotcCategoryCounts->sum());

            return $unspecifiedCount > 0
                ? $categoryRows->push(['code' => 'ROTC-Unset', 'name' => 'ROTC category not set', 'count' => $unspecifiedCount])
                : $categoryRows;
        })->values();
        $largestComponentCount = max(1, (int) $componentEnrollments->max('count'));
        $componentEnrollments = $componentEnrollments->map(fn (array $component): array => $component + [
            'percentage' => $component['count'] > 0 ? max(10, ($component['count'] / $largestComponentCount) * 100) : 0,
        ]);
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
            'componentEnrollments' => $componentEnrollments,
            'componentEnrollmentTotal' => (int) $componentEnrollments->sum('count'),
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
