<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TYPES = [
        'students' => 'Student Masterlist',
        'attendance' => 'Attendance Report',
        'grades' => 'Grade Report',
        'sections' => 'Component and Section Report',
    ];

    public function __construct(private GradeService $grades) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $report = $this->buildReport($filters);
        $routePrefix = $request->user()->isNstpAdmin() ? 'nstp_admin' : 'admin';
        $layout = $request->user()->isNstpAdmin() ? 'layouts.nstp-admin' : 'layouts.admin';

        return view('admin.reports.index', [
            'layout' => $layout,
            'routePrefix' => $routePrefix,
            'filters' => $filters,
            'report' => $report,
            'reportTypes' => self::TYPES,
            'components' => NstpComponent::orderBy('code')->get(),
            'sections' => NstpSection::with('component')->orderBy('code')->get(),
            'academicYears' => NstpSection::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'metrics' => [
                'students' => User::where('role', 'student')->count(),
                'attendance_rate' => $this->attendanceRate(),
                'graded' => AssessmentSubmission::whereNotNull('score')->count(),
                'sections' => NstpSection::count(),
            ],
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        $filters = $this->filters($request, $type);
        $report = $this->buildReport($filters);
        $filename = str($report['title'])->slug().'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [$report['title']]);
            fputcsv($output, ['Generated', now()->format('F d, Y h:i A')]);
            fputcsv($output, []);
            fputcsv($output, $report['headers']);
            foreach ($report['rows'] as $row) {
                fputcsv($output, array_values($row));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function print(Request $request, string $type): View
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);
        $filters = $this->filters($request, $type);

        return view('admin.reports.print', ['report' => $this->buildReport($filters), 'filters' => $filters]);
    }

    private function filters(Request $request, ?string $forcedType = null): array
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(self::TYPES))],
            'academic_year' => ['nullable', 'string', 'max:9'],
            'semester' => ['nullable', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'section_id' => ['nullable', 'integer', 'exists:nstp_sections,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $validated['type'] = $forcedType ?? ($validated['type'] ?? 'students');

        return $validated;
    }

    private function buildReport(array $filters): array
    {
        return match ($filters['type']) {
            'attendance' => $this->attendanceReport($filters),
            'grades' => $this->gradeReport($filters),
            'sections' => $this->sectionReport($filters),
            default => $this->studentReport($filters),
        };
    }

    private function studentReport(array $filters): array
    {
        $hasEnrollmentFilters = collect($filters)->only(['academic_year', 'semester', 'component_id', 'section_id'])->filter()->isNotEmpty();
        $enrollmentFilter = function ($query) use ($filters): void {
            $query->when($filters['academic_year'] ?? null, fn ($q, $value) => $q->where('academic_year', $value))
                ->when($filters['semester'] ?? null, fn ($q, $value) => $q->where('semester', $value))
                ->when($filters['component_id'] ?? null, fn ($q, $value) => $q->where('component_id', $value))
                ->when($filters['section_id'] ?? null, fn ($q, $value) => $q->where('section_id', $value));
        };
        $rows = User::where('role', 'student')
            ->with(['nstpEnrollments' => fn ($query) => $enrollmentFilter($query->with(['component', 'section.facilitator'])->latest('academic_year')->latest('semester'))])
            ->when($hasEnrollmentFilters, fn ($query) => $query->whereHas('nstpEnrollments', $enrollmentFilter))
            ->orderBy('name')->get()->map(function ($student) {
                $enrollment = $student->nstpEnrollments->first();

                return [
                    'student' => $student->name,
                    'email' => $student->email,
                    'component' => $enrollment?->component?->code ?? 'Unassigned',
                    'section' => $enrollment?->section?->code ?? 'Unassigned',
                    'term' => $enrollment ? (($enrollment->section?->semesterLabel() ?? str($enrollment->semester)->headline()).' '.$enrollment->academic_year) : 'Not enrolled',
                    'facilitator' => $enrollment?->section?->facilitator?->name ?? 'Unassigned',
                    'status' => $enrollment ? ucfirst($enrollment->status) : $student->statusLabel(),
                ];
            })->values();

        return $this->report('Student Masterlist', ['Student', 'Email', 'Component', 'Section', 'Term', 'Facilitator', 'Status'], $rows);
    }

    private function attendanceReport(array $filters): array
    {
        $rows = AttendanceRecord::with(['student', 'attendanceSession.section.component'])
            ->whereHas('attendanceSession.section', fn ($q) => $this->applySectionFilters($q, $filters))
            ->whereHas('attendanceSession', function ($q) use ($filters) {
                $q->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', '>=', $date))
                    ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', '<=', $date));
            })->latest('checked_in_at')->get()->map(function ($item) {
                $session = $item->attendanceSession;

                return [
                    'student' => $item->student->name, 'component' => $session->section->component->code,
                    'section' => $session->section->code, 'session' => $session->title,
                    'date' => $session->starts_at->format('M d, Y'), 'status' => ucfirst($item->status),
                    'check_in' => $item->checked_in_at?->format('h:i:s A') ?? '—', 'source' => strtoupper($item->source),
                ];
            });

        return $this->report('Attendance Report', ['Student', 'Component', 'Section', 'Session', 'Date', 'Status', 'Check-in', 'Source'], $rows);
    }

    private function gradeReport(array $filters): array
    {
        $rows = NstpEnrollment::with(['student', 'component', 'section'])
            ->whereNotNull('section_id')->whereHas('section', fn ($q) => $this->applySectionFilters($q, $filters))
            ->get()->sortBy(fn ($item) => $item->student->name)->map(function ($item) {
                $summary = $this->grades->summary($item->student, $item->section_id);

                return [
                    'student' => $item->student->name, 'component' => $item->component->code, 'section' => $item->section->code,
                    'graded' => $summary['graded_count'].' of '.$summary['total_count'],
                    'raw_score_rate' => $summary['raw_percentage'] === null ? '—' : number_format($summary['raw_percentage'], 2).'%',
                    'percentage' => $summary['percentage'] === null ? '—' : number_format($summary['percentage'], 2).'%',
                    'grade' => $summary['grade'] === null ? '—' : number_format($summary['grade'], 2),
                    'status' => $summary['total_count'] > 0 && $summary['graded_count'] === $summary['total_count'] ? 'Complete' : 'In progress',
                ];
            })->values();

        return $this->report('Grade Report', ['Student', 'Component', 'Section', 'Graded Score Items', 'Raw Score Rate', 'Weighted Total', 'Final Grade', 'Status'], $rows);
    }

    private function sectionReport(array $filters): array
    {
        $sections = NstpSection::with(['component', 'facilitator', 'enrollments.student'])->withCount(['enrollments', 'attendanceSessions', 'assessments']);
        $this->applySectionFilters($sections, $filters);
        $rows = $sections->orderBy('code')->get()->map(function ($section) {
            $grades = $section->enrollments->map(fn ($item) => $this->grades->summary($item->student, $section->id)['grade'])->filter(fn ($grade) => $grade !== null);

            return [
                'component' => $section->component->code, 'section' => $section->code,
                'term' => $section->semesterLabel().' '.$section->academic_year, 'facilitator' => $section->facilitator?->name ?? 'Unassigned',
                'enrollment' => $section->enrollments_count.' / '.$section->capacity,
                'utilization' => $section->capacity ? number_format(($section->enrollments_count / $section->capacity) * 100, 1).'%' : '0%',
                'sessions' => $section->attendance_sessions_count, 'assessments' => $section->assessments_count,
                'average_grade' => $grades->isEmpty() ? '—' : number_format($grades->average(), 2),
            ];
        });

        return $this->report('Component and Section Report', ['Component', 'Section', 'Term', 'Facilitator', 'Enrollment', 'Utilization', 'Attendance Sessions', 'Assessments', 'Average Grade'], $rows);
    }

    private function applySectionFilters($query, array $filters)
    {
        return $query->when($filters['academic_year'] ?? null, fn ($q, $value) => $q->where('academic_year', $value))
            ->when($filters['semester'] ?? null, fn ($q, $value) => $q->where('semester', $value))
            ->when($filters['component_id'] ?? null, fn ($q, $value) => $q->where('component_id', $value))
            ->when($filters['section_id'] ?? null, fn ($q, $value) => $q->where('id', $value));
    }

    private function report(string $title, array $headers, Collection $rows): array
    {
        return compact('title', 'headers', 'rows') + ['generated_at' => now()];
    }

    private function attendanceRate(): float
    {
        $total = AttendanceRecord::count();

        return $total ? round((AttendanceRecord::whereIn('status', ['present', 'late'])->count() / $total) * 100, 1) : 0;
    }
}
