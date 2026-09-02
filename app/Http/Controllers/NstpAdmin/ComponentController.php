<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComponentController extends Controller
{
    public function index(Request $request): View
    {
        [$defaultAcademicYear, $defaultSemester] = $this->currentTerm();
        $components = NstpComponent::query()
            ->orderByRaw("CASE code WHEN 'CWTS' THEN 1 WHEN 'LTS' THEN 2 WHEN 'ROTC' THEN 3 ELSE 4 END")
            ->get();

        $validated = $request->validate([
            'component' => ['nullable', Rule::in($components->pluck('id')->map(fn ($id) => (string) $id)->prepend('all')->all())],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', 'in:'.implode(',', array_keys(NstpSection::SEMESTERS))],
            'ms_level' => ['nullable', 'in:'.implode(',', array_keys(NstpEnrollment::ROTC_CATEGORIES))],
        ]);

        $academicYear = $validated['academic_year'] ?? $defaultAcademicYear;
        $semester = $validated['semester'] ?? $defaultSemester;
        $compareAllComponents = ($validated['component'] ?? null) === 'all';
        $selectedComponent = $compareAllComponents
            ? null
            : ($components->firstWhere('id', (int) ($validated['component'] ?? 0)) ?? $components->first());
        $msLevel = $selectedComponent?->code === 'ROTC' ? ($validated['ms_level'] ?? null) : null;

        $termEnrollments = NstpEnrollment::query()
            ->whereIn('status', ['enrolled', 'pending_approval'])
            ->where('academic_year', $academicYear)
            ->where('semester', $semester);

        $componentCounts = (clone $termEnrollments)
            ->selectRaw('component_id, COUNT(DISTINCT student_id) as total')
            ->groupBy('component_id')
            ->pluck('total', 'component_id');

        $componentEnrollments = $components->map(fn (NstpComponent $component) => [
            'code' => $component->code,
            'name' => $component->name,
            'count' => (int) ($componentCounts[$component->id] ?? 0),
        ]);

        $selectedEnrollments = (clone $termEnrollments)
            ->when(! $compareAllComponents, fn (Builder $query) => $query->where('component_id', $selectedComponent?->id ?? 0))
            ->when($msLevel, fn (Builder $query, string $level) => $query->where('rotc_category', $level));
        $fields = ['college', 'course', 'province', 'sex'];
        $breakdowns = $compareAllComponents
            ? []
            : collect($fields)->mapWithKeys(fn (string $field) => [
                $field => $this->profileBreakdown($selectedEnrollments, $field),
            ])->all();
        $comparisonBreakdowns = $compareAllComponents
            ? collect($fields)->mapWithKeys(fn (string $field) => [
                $field => $this->componentProfileComparison($selectedEnrollments, $field, $components),
            ])->all()
            : [];
        $comparisonMaximums = collect($comparisonBreakdowns)->map(fn (Collection $rows) => max(
            1,
            (int) $rows->flatMap(fn (array $row) => $row['counts']->values())->max(),
        ))->all();

        return view('nstp_admin.components.index', [
            'components' => $components,
            'componentEnrollments' => $componentEnrollments,
            'selectedComponent' => $selectedComponent,
            'compareAllComponents' => $compareAllComponents,
            'selectedEnrollmentCount' => (clone $selectedEnrollments)->distinct()->count('student_id'),
            'academicYear' => $academicYear,
            'semester' => $semester,
            'msLevel' => $msLevel,
            'academicYears' => NstpEnrollment::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year')
                ->prepend($academicYear)->unique()->values(),
            'rotcCategories' => NstpEnrollment::ROTC_CATEGORIES,
            'breakdowns' => $breakdowns,
            'comparisonBreakdowns' => $comparisonBreakdowns,
            'comparisonMaximums' => $comparisonMaximums,
            'routePrefix' => $this->routePrefix($request),
            'componentSelectionOpen' => SystemSetting::componentSelectionIsOpen(),
            'componentSelectionSetting' => SystemSetting::with('updater')->find('component_selection_open'),
        ]);
    }

    public function updateSelectionAvailability(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_open' => ['required', 'boolean'],
        ]);
        $isOpen = (bool) $validated['is_open'];

        SystemSetting::updateOrCreate(
            ['key' => 'component_selection_open'],
            ['value' => $isOpen ? '1' : '0', 'updated_by' => $request->user()->id],
        );

        return back()->with('status', 'Student NSTP selection is now '.($isOpen ? 'open' : 'closed').'.');
    }

    public function edit(Request $request, NstpComponent $component): View
    {
        $component->loadCount(['sections', 'enrollments']);

        return view('nstp_admin.components.edit', [
            'component' => $component,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function update(Request $request, NstpComponent $component): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_section_capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'is_active' => ['required', 'boolean'],
        ]);

        $component->update($validated);

        return redirect()->route($this->routePrefix($request).'.sections.index')
            ->with('status', "{$component->code} configuration updated successfully.");
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }

    private function profileBreakdown(Builder $enrollments, string $field): Collection
    {
        $column = 'student_profiles.'.$field;

        return (clone $enrollments)
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'nstp_enrollments.student_id')
            ->selectRaw("{$column} as label, COUNT(DISTINCT nstp_enrollments.student_id) as total")
            ->groupBy($column)
            ->get()
            ->map(fn ($row) => [
                'label' => filled(trim((string) $row->label)) ? trim((string) $row->label) : 'Not provided',
                'count' => (int) $row->total,
            ])
            ->groupBy('label')
            ->map(fn (Collection $rows, string $label) => [
                'label' => $label,
                'count' => (int) $rows->sum('count'),
            ])
            ->sort(fn (array $left, array $right) => $right['count'] <=> $left['count'] ?: strcasecmp($left['label'], $right['label']))
            ->values();
    }

    private function componentProfileComparison(Builder $enrollments, string $field, Collection $components): Collection
    {
        $column = 'student_profiles.'.$field;

        return (clone $enrollments)
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'nstp_enrollments.student_id')
            ->selectRaw("nstp_enrollments.component_id, {$column} as label, COUNT(DISTINCT nstp_enrollments.student_id) as total")
            ->groupBy('nstp_enrollments.component_id', $column)
            ->get()
            ->map(fn ($row) => [
                'component_id' => (int) $row->component_id,
                'label' => filled(trim((string) $row->label)) ? trim((string) $row->label) : 'Not provided',
                'count' => (int) $row->total,
            ])
            ->groupBy('label')
            ->map(function (Collection $rows, string $label) use ($components): array {
                $counts = $components->mapWithKeys(fn (NstpComponent $component) => [
                    $component->code => (int) $rows->where('component_id', $component->id)->sum('count'),
                ]);

                return ['label' => $label, 'total' => (int) $counts->sum(), 'counts' => $counts];
            })
            ->sort(fn (array $left, array $right) => $right['total'] <=> $left['total'] ?: strcasecmp($left['label'], $right['label']))
            ->values();
    }

    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
