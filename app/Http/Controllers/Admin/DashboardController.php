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
        ]);
    }

    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
