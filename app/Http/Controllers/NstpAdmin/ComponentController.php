<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'component' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', 'in:'.implode(',', array_keys(NstpSection::SEMESTERS))],
            'ms_level' => ['nullable', 'in:'.implode(',', array_keys(NstpEnrollment::ROTC_CATEGORIES))],
        ]);

        $academicYear = $validated['academic_year'] ?? $defaultAcademicYear;
        $semester = $validated['semester'] ?? $defaultSemester;
        $selectedComponent = $components->firstWhere('id', (int) ($validated['component'] ?? 0))
            ?? $components->first();
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
            ->where('component_id', $selectedComponent?->id ?? 0)
            ->when($msLevel, fn (Builder $query, string $level) => $query->where('rotc_category', $level));

        return view('nstp_admin.components.index', [
            'components' => $components,
            'componentEnrollments' => $componentEnrollments,
            'selectedComponent' => $selectedComponent,
            'selectedEnrollmentCount' => (clone $selectedEnrollments)->distinct()->count('student_id'),
            'academicYear' => $academicYear,
            'semester' => $semester,
            'msLevel' => $msLevel,
            'academicYears' => NstpEnrollment::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year')
                ->prepend($academicYear)->unique()->values(),
            'rotcCategories' => NstpEnrollment::ROTC_CATEGORIES,
            'breakdowns' => [
                'college' => $this->profileBreakdown($selectedEnrollments, 'college'),
                'course' => $this->profileBreakdown($selectedEnrollments, 'course'),
                'province' => $this->profileBreakdown($selectedEnrollments, 'province'),
                'sex' => $this->profileBreakdown($selectedEnrollments, 'sex'),
            ],
            'routePrefix' => $this->routePrefix($request),
        ]);
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

    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
