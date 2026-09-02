<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SectioningController extends Controller
{
    public function index(Request $request): View
    {
        $showAllComponents = $request->input('component_id') === 'all';
        if ($showAllComponents) {
            $request->merge(['component_id' => null]);
        }

        $term = $this->validatedTerm($request, true);
        $academicYear = $term['academic_year'] ?? $this->currentAcademicYear();
        $semester = $term['semester'] ?? $this->currentSemester();
        $components = NstpComponent::where('is_active', true)
            ->orderByRaw("CASE code WHEN 'CWTS' THEN 1 WHEN 'LTS' THEN 2 WHEN 'ROTC' THEN 3 ELSE 4 END")
            ->get();
        $componentId = ! $showAllComponents && isset($term['component_id'])
            ? (int) $term['component_id']
            : ($showAllComponents ? null : $components->first()?->id);
        $selectedComponent = $components->firstWhere('id', $componentId);

        $sections = NstpSection::with(['component', 'facilitator'])
            ->withCount('enrollments')
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->when($componentId, fn ($query) => $query->where('component_id', $componentId))
            ->orderBy('code')
            ->get();

        $unsectionedCounts = NstpEnrollment::query()
            ->select('component_id', DB::raw('count(*) as total'))
            ->where('status', 'enrolled')
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->whereNull('section_id')
            ->groupBy('component_id')
            ->pluck('total', 'component_id');

        return view('nstp_admin.sectioning.index', [
            'components' => $components,
            'sections' => $sections,
            'academicYear' => $academicYear,
            'semester' => $semester,
            'componentId' => $componentId,
            'unsectionedCounts' => $unsectionedCounts,
            'selectedComponent' => $selectedComponent,
            'showAllComponents' => $showAllComponents,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function automate(Request $request): RedirectResponse
    {
        $showAllComponents = $request->input('component_id') === 'all';
        $rules = $this->termRules();
        if ($showAllComponents) {
            $rules['component_id'] = ['required', Rule::in(['all'])];
        }

        $validated = $request->validate($rules);
        $components = $showAllComponents
            ? NstpComponent::where('is_active', true)->orderBy('id')->get()
            : collect([NstpComponent::findOrFail($validated['component_id'])]);
        $assignedCount = 0;
        $createdCount = 0;

        DB::transaction(function () use ($validated, $components, &$assignedCount, &$createdCount): void {
            foreach ($components as $component) {
                $unassigned = NstpEnrollment::where('component_id', $component->id)
                    ->where('status', 'enrolled')
                    ->where('academic_year', $validated['academic_year'])
                    ->where('semester', $validated['semester'])
                    ->whereNull('section_id')
                    ->with('student')
                    ->get()
                    ->sortBy(fn ($enrollment) => $enrollment->student->name, SORT_NATURAL | SORT_FLAG_CASE)
                    ->values();

                if ($unassigned->isEmpty()) {
                    continue;
                }

                $sections = NstpSection::where('component_id', $component->id)
                    ->where('academic_year', $validated['academic_year'])
                    ->where('semester', $validated['semester'])
                    ->where('status', 'active')
                    ->withCount('enrollments')
                    ->lockForUpdate()
                    ->get();

                $nextNumber = $sections->count() + 1;

                foreach ($unassigned as $enrollment) {
                    $section = $sections
                        ->filter(fn ($candidate) => $candidate->enrollments_count < $candidate->capacity)
                        ->sortBy(fn ($candidate) => $candidate->enrollments_count / $candidate->capacity)
                        ->first();

                    if (! $section) {
                        do {
                            $code = $component->code.'-'.str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
                            $nextNumber++;
                        } while (NstpSection::where('academic_year', $validated['academic_year'])
                            ->where('semester', $validated['semester'])
                            ->where('code', $code)
                            ->exists());

                        $section = NstpSection::create([
                            'component_id' => $component->id,
                            'code' => $code,
                            'name' => $component->code.' Section '.($nextNumber - 1),
                            'academic_year' => $validated['academic_year'],
                            'semester' => $validated['semester'],
                            'capacity' => $component->default_section_capacity,
                            'status' => 'active',
                        ]);
                        $section->enrollments_count = 0;
                        $sections->push($section);
                        $createdCount++;
                    }

                    $enrollment->update(['section_id' => $section->id]);
                    $section->enrollments_count++;
                    $assignedCount++;
                }
            }
        });

        $message = $assignedCount
            ? "Automated sectioning assigned {$assignedCount} student(s) and created {$createdCount} new section(s)."
            : 'No unsectioned students were found for the selected component(s) and term.';

        return redirect()->route($this->routePrefix($request).'.sections.index', $this->termQuery($validated))
            ->with('status', $message);
    }

    private function validatedTerm(Request $request, bool $optional = false): array
    {
        $rules = $this->termRules();

        if ($optional) {
            $rules = collect($rules)->map(fn ($ruleSet) => array_merge(['nullable'], array_slice($ruleSet, 1)))->all();
        }

        return $request->validate($rules);
    }

    private function termRules(): array
    {
        return [
            'component_id' => ['required', 'integer', Rule::exists('nstp_components', 'id')->where('is_active', true)],
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/', function (string $attribute, mixed $value, \Closure $fail): void {
                [$start, $end] = array_map('intval', explode('-', $value));
                if ($end !== $start + 1) {
                    $fail('The academic year must contain consecutive years, for example 2026-2027.');
                }
            }],
            'semester' => ['required', Rule::in(array_keys(NstpSection::SEMESTERS))],
        ];
    }

    private function termQuery(array $values): array
    {
        return [
            'component_id' => $values['component_id'],
            'academic_year' => $values['academic_year'],
            'semester' => $values['semester'],
        ];
    }

    private function currentAcademicYear(): string
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return $start.'-'.($start + 1);
    }

    private function currentSemester(): string
    {
        return now()->month >= 6 ? 'first' : 'second';
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
