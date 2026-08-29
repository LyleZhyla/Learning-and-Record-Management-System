<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function __construct(private GradeService $grades) {}

    public function components(): View
    {
        $components = NstpComponent::withCount(['sections', 'enrollments'])
            ->with(['sections' => fn ($query) => $query->with(['facilitator'])->withCount('enrollments')->orderBy('code')])
            ->orderBy('code')->get();

        return view('coordinator.components', compact('components'));
    }

    public function sections(Request $request): View
    {
        $filters = $this->filters($request);
        $sections = NstpSection::with(['component', 'facilitator'])->withCount(['enrollments', 'attendanceSessions', 'assessments'])
            ->when($filters['component_id'] ?? null, fn ($query, $value) => $query->where('component_id', $value))
            ->when($filters['academic_year'] ?? null, fn ($query, $value) => $query->where('academic_year', $value))
            ->when($filters['semester'] ?? null, fn ($query, $value) => $query->where('semester', $value))
            ->orderByDesc('academic_year')->orderBy('code')->paginate(15)->withQueryString();

        return view('coordinator.sections', $this->filterOptions() + compact('sections', 'filters'));
    }

    public function attendance(Request $request): View
    {
        $filters = $this->filters($request, true);
        $sessions = AttendanceSession::with(['section.component', 'creator'])
            ->withCount([
                'records',
                'records as present_count' => fn ($query) => $query->where('status', 'present'),
                'records as late_count' => fn ($query) => $query->where('status', 'late'),
                'records as absent_count' => fn ($query) => $query->where('status', 'absent'),
            ])
            ->when($filters['component_id'] ?? null, fn ($query, $value) => $query->whereHas('section', fn ($q) => $q->where('component_id', $value)))
            ->when($filters['section_id'] ?? null, fn ($query, $value) => $query->where('section_id', $value))
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('starts_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('starts_at', '<=', $value))
            ->latest('starts_at')->paginate(15)->withQueryString();

        return view('coordinator.attendance', $this->filterOptions() + compact('sessions', 'filters'));
    }

    public function performance(Request $request): View
    {
        $filters = $this->filters($request);
        $sections = NstpSection::with('component')->orderByDesc('academic_year')->orderBy('code')->get();
        $section = $sections->firstWhere('id', (int) ($filters['section_id'] ?? 0)) ?? $sections->first();
        $summaries = collect();

        if ($section) {
            $section->load(['component', 'enrollments.student']);
            $summaries = $section->enrollments->map(fn ($enrollment) => [
                'student' => $enrollment->student,
                ...$this->grades->summary($enrollment->student, $section->id),
            ])->sortBy(fn ($item) => $item['student']->name)->values();
        }

        return view('coordinator.performance', compact('sections', 'section', 'summaries', 'filters'));
    }

    private function filters(Request $request, bool $dates = false): array
    {
        return $request->validate([
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'section_id' => ['nullable', 'integer', 'exists:nstp_sections,id'],
            'academic_year' => ['nullable', 'string', 'max:9'],
            'semester' => ['nullable', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'date_from' => [$dates ? 'nullable' : 'prohibited', 'date'],
            'date_to' => [$dates ? 'nullable' : 'prohibited', 'date', 'after_or_equal:date_from'],
        ]);
    }

    private function filterOptions(): array
    {
        return [
            'components' => NstpComponent::orderBy('code')->get(),
            'allSections' => NstpSection::with('component')->orderBy('code')->get(),
            'academicYears' => NstpSection::distinct()->orderByDesc('academic_year')->pluck('academic_year'),
        ];
    }
}
