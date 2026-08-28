<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'component' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['nullable', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $sections = NstpSection::with(['component', 'facilitator'])
            ->withCount('enrollments')
            ->when($filters['component'] ?? null, fn ($query, $component) => $query->where('component_id', $component))
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->where('academic_year', $year))
            ->when($filters['semester'] ?? null, fn ($query, $semester) => $query->where('semester', $semester))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('academic_year')
            ->orderByRaw("FIELD(semester, 'first', 'second', 'summer')")
            ->orderBy('code')
            ->paginate(12)
            ->withQueryString();

        return view('nstp_admin.sections.index', [
            'sections' => $sections,
            'components' => NstpComponent::orderBy('code')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View
    {
        return view('nstp_admin.sections.create', [
            'section' => null,
            'components' => NstpComponent::where('is_active', true)->orderBy('code')->get(),
            'facilitators' => $this->facilitators(),
            'selectedComponent' => $request->integer('component') ?: null,
            'defaultAcademicYear' => $this->currentAcademicYear(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->sectionRules());

        $section = NstpSection::create($validated);

        return redirect()->route('nstp_admin.sections.edit', $section)
            ->with('status', "Section {$section->code} created successfully.");
    }

    public function edit(NstpSection $section): View
    {
        $section->loadCount('enrollments');

        return view('nstp_admin.sections.edit', [
            'section' => $section,
            'components' => NstpComponent::orderBy('code')->get(),
            'facilitators' => $this->facilitators(),
            'selectedComponent' => $section->component_id,
            'defaultAcademicYear' => $section->academic_year,
        ]);
    }

    public function update(Request $request, NstpSection $section): RedirectResponse
    {
        $validated = $request->validate($this->sectionRules($section));
        $enrollmentCount = $section->enrollments()->count();

        if ($enrollmentCount > 0 && (
            (int) $validated['component_id'] !== $section->component_id
            || $validated['academic_year'] !== $section->academic_year
            || $validated['semester'] !== $section->semester
        )) {
            throw ValidationException::withMessages([
                'component_id' => 'A populated section cannot be moved to another component or academic term.',
            ]);
        }

        if ((int) $validated['capacity'] < $enrollmentCount) {
            throw ValidationException::withMessages([
                'capacity' => "Capacity cannot be lower than the {$enrollmentCount} currently assigned students.",
            ]);
        }

        if ($validated['status'] === 'inactive' && $enrollmentCount > 0) {
            throw ValidationException::withMessages([
                'status' => 'Move all assigned students before deactivating this section.',
            ]);
        }

        $section->update($validated);

        return back()->with('status', "Section {$section->code} updated successfully.");
    }

    private function sectionRules(?NstpSection $section = null): array
    {
        $uniqueCode = Rule::unique('nstp_sections', 'code')
            ->where(fn ($query) => $query
                ->where('academic_year', request('academic_year'))
                ->where('semester', request('semester')))
            ->ignore($section?->id);

        return [
            'component_id' => ['required', 'integer', 'exists:nstp_components,id'],
            'facilitator_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'facilitator')->where('status', 'active')),
            ],
            'code' => ['required', 'string', 'max:30', $uniqueCode],
            'name' => ['required', 'string', 'max:100'],
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/', function (string $attribute, mixed $value, \Closure $fail): void {
                [$start, $end] = array_map('intval', explode('-', $value));
                if ($end !== $start + 1) {
                    $fail('The academic year must contain consecutive years, for example 2026-2027.');
                }
            }],
            'semester' => ['required', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function facilitators()
    {
        return User::where('role', 'facilitator')->where('status', 'active')->orderBy('name')->get();
    }

    private function currentAcademicYear(): string
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return $start.'-'.($start + 1);
    }
}
