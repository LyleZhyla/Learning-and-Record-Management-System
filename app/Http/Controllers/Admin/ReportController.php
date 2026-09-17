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
use App\Services\ReportDocumentService;
use App\Services\ReportSpreadsheetService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public const TYPES = [
        'students' => 'Student Masterlist',
        'students_by_section' => 'Students by Section',
        'attendance' => 'Attendance Report',
        'grades' => 'Grade Report',
        'sections' => 'Component and Section Report',
    ];

    public function __construct(
        private GradeService $grades,
        private ReportSpreadsheetService $spreadsheets,
        private ReportDocumentService $documents,
    ) {}

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $report = $this->buildReport($filters);
        $isCoordinator = $request->user()->isCoordinator();
        $isFacilitator = $request->user()->isFacilitator();
        $componentId = $isCoordinator ? $request->user()->nstp_component_id : null;
        $facilitatorId = $isFacilitator ? $request->user()->id : null;
        $routePrefix = $this->routePrefix($request);
        $layout = $isCoordinator ? 'layouts.coordinator' : ($isFacilitator ? 'layouts.facilitator' : ($request->user()->isNstpAdmin() ? 'layouts.nstp-admin' : 'layouts.admin'));
        $components = NstpComponent::query()
            ->when($isCoordinator, fn ($query) => $query->whereKey($componentId ?? 0))
            ->when($isFacilitator, fn ($query) => $query->whereHas('sections', fn ($section) => $section->where('facilitator_id', $facilitatorId)))
            ->orderBy('code')->get();
        $sections = NstpSection::with('component')
            ->when($isCoordinator, fn ($query) => $query->where('component_id', $componentId ?? 0))
            ->when($isFacilitator, fn ($query) => $query->where('facilitator_id', $facilitatorId))
            ->orderBy('code')->get();
        $academicYears = NstpSection::query()
            ->when($isCoordinator, fn ($query) => $query->where('component_id', $componentId ?? 0))
            ->when($isFacilitator, fn ($query) => $query->where('facilitator_id', $facilitatorId))
            ->distinct()->orderByDesc('academic_year')->pluck('academic_year');
        $attendanceTrend = $this->attendanceTrend($filters);
        $enrollmentBreakdown = $this->enrollmentBreakdown($filters, $components);
        $largestEnrollmentCount = max(1, (int) $enrollmentBreakdown->max('count'));
        $enrollmentBreakdown = $enrollmentBreakdown->map(fn (array $component): array => $component + [
            'percentage' => $component['count'] > 0 ? max(8, ($component['count'] / $largestEnrollmentCount) * 100) : 0,
        ]);

        return view('admin.reports.index', [
            'layout' => $layout,
            'routePrefix' => $routePrefix,
            'filters' => $filters,
            'report' => $report,
            'reportTypes' => $this->availableReportTypes($request),
            'components' => $components,
            'sections' => $sections,
            'academicYears' => $academicYears,
            'attendanceChart' => $this->attendanceChart($attendanceTrend),
            'enrollmentBreakdown' => $enrollmentBreakdown,
            'enrollmentTotal' => (int) $enrollmentBreakdown->sum('count'),
            'isCoordinatorReport' => $isCoordinator,
            'isFacilitatorReport' => $isFacilitator,
            'reportScope' => $isCoordinator ? ($request->user()->nstpComponent?->code ?? 'Unassigned component') : ($isFacilitator ? 'Assigned sections' : 'Institution-wide'),
            'metrics' => [
                'students' => $isCoordinator
                    ? NstpEnrollment::where('component_id', $componentId ?? 0)->where('status', 'enrolled')->distinct()->count('student_id')
                    : ($isFacilitator
                        ? NstpEnrollment::whereHas('section', fn ($section) => $section->where('facilitator_id', $facilitatorId))->where('status', 'enrolled')->distinct()->count('student_id')
                        : User::where('role', 'student')->count()),
                'attendance_rate' => $this->attendanceRate($isCoordinator ? ($componentId ?? 0) : null, $facilitatorId),
                'graded' => AssessmentSubmission::whereNotNull('score')
                    ->when($isCoordinator, fn ($query) => $query->whereHas('assessment.section', fn ($section) => $section->where('component_id', $componentId ?? 0)))
                    ->when($isFacilitator, fn ($query) => $query->whereHas('assessment.section', fn ($section) => $section->where('facilitator_id', $facilitatorId)))
                    ->count(),
                'sections' => NstpSection::query()
                    ->when($isCoordinator, fn ($query) => $query->where('component_id', $componentId ?? 0))
                    ->when($isFacilitator, fn ($query) => $query->where('facilitator_id', $facilitatorId))
                    ->count(),
            ],
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(array_key_exists($type, $this->availableReportTypes($request)), 404);
        $filters = $this->filters($request, $type);
        $report = $this->selectDownloadColumns($request, $this->buildReport($filters));
        $spreadsheet = $this->spreadsheets->create($report, $this->filterSummary($filters));
        $filename = str($report['title'])->slug().'-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function print(Request $request, string $type): View
    {
        abort_unless(array_key_exists($type, $this->availableReportTypes($request)), 404);
        $filters = $this->filters($request, $type);

        return view('admin.reports.print', [
            'report' => $this->buildReport($filters),
            'filters' => $filters,
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function document(Request $request, string $type): BinaryFileResponse
    {
        abort_unless(array_key_exists($type, $this->availableReportTypes($request)), 404);
        $filters = $this->filters($request, $type);
        $report = $this->selectDownloadColumns($request, $this->buildReport($filters));
        $document = $this->documents->create($report, $this->filterSummary($filters));
        $filename = str($report['title'])->slug().'-'.now()->format('Y-m-d-His').'.docx';

        return response()->download($document, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, string $type): Response
    {
        abort_unless(array_key_exists($type, $this->availableReportTypes($request)), 404);
        $filters = $this->filters($request, $type);
        $report = $this->selectDownloadColumns($request, $this->buildReport($filters));
        $logoPath = public_path('images/snapie-logo-160.png');
        $logo = is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null;

        $options = new Options;
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('admin.reports.pdf', [
            'report' => $report,
            'filterSummary' => $this->filterSummary($filters),
            'logo' => $logo,
        ])->render());
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('Helvetica');
        $canvas->page_text(
            $canvas->get_width() - 105,
            $canvas->get_height() - 22,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            8,
            [0.39, 0.45, 0.55],
        );

        $filename = str($report['title'])->slug().'-'.now()->format('Y-m-d-His').'.pdf';

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function filters(Request $request, ?string $forcedType = null): array
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(array_keys($this->availableReportTypes($request)))],
            'academic_year' => ['nullable', 'string', 'max:9'],
            'semester' => ['nullable', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'component_id' => ['nullable', 'integer', 'exists:nstp_components,id'],
            'section_id' => ['nullable', 'integer', 'exists:nstp_sections,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $validated['type'] = $forcedType ?? ($validated['type'] ?? 'students');

        if ($request->user()->isCoordinator()) {
            $validated['component_id'] = $request->user()->nstp_component_id ?? 0;
        }
        if ($request->user()->isFacilitator()) {
            $validated['facilitator_id'] = $request->user()->id;
        }

        return $validated;
    }

    private function buildReport(array $filters): array
    {
        return match ($filters['type']) {
            'students_by_section' => $this->studentsBySectionReport($filters),
            'attendance' => $this->attendanceReport($filters),
            'grades' => $this->gradeReport($filters),
            'sections' => $this->sectionReport($filters),
            default => $this->studentReport($filters),
        };
    }

    private function selectDownloadColumns(Request $request, array $report): array
    {
        if (! $request->has('columns')) {
            return $report;
        }

        $validated = $request->validate([
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'integer', 'distinct', 'min:0', 'max:'.(count($report['headers']) - 1)],
        ]);
        $indexes = collect($validated['columns'])->map(fn ($index) => (int) $index)->sort()->values();
        $selectValues = fn (array $row): array => $indexes
            ->map(fn (int $index) => array_values($row)[$index])
            ->all();

        $report['headers'] = $indexes->map(fn (int $index) => $report['headers'][$index])->all();
        $report['rows'] = $report['rows']->map($selectValues);

        if (array_key_exists('groups', $report)) {
            $report['groups'] = $report['groups']->map(function (array $group) use ($selectValues): array {
                $group['rows'] = $group['rows']->map($selectValues);

                return $group;
            });
        }

        return $report;
    }

    private function studentReport(array $filters): array
    {
        $hasEnrollmentFilters = collect($filters)->only(['academic_year', 'semester', 'component_id', 'section_id', 'facilitator_id'])->filter()->isNotEmpty();
        $enrollmentFilter = function ($query) use ($filters): void {
            $query->when($filters['academic_year'] ?? null, fn ($q, $value) => $q->where('academic_year', $value))
                ->when($filters['semester'] ?? null, fn ($q, $value) => $q->where('semester', $value))
                ->when($filters['component_id'] ?? null, fn ($q, $value) => $q->where('component_id', $value))
                ->when($filters['section_id'] ?? null, fn ($q, $value) => $q->where('section_id', $value))
                ->when($filters['facilitator_id'] ?? null, fn ($q, $value) => $q->whereHas('section', fn ($section) => $section->where('facilitator_id', $value)));
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

    private function studentsBySectionReport(array $filters): array
    {
        $studentReport = $this->studentReport($filters);
        $groups = $studentReport['rows']
            ->groupBy(fn (array $row): string => implode('|', [$row['component'], $row['section'], $row['term']]))
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $isUnassigned = $first['section'] === 'Unassigned';

                return [
                    'title' => $isUnassigned ? 'Unassigned Students' : $first['component'].' - '.$first['section'],
                    'subtitle' => $isUnassigned
                        ? 'Students without a section assignment'
                        : $first['term'].' | Facilitator: '.$first['facilitator'],
                    'sheet_name' => $isUnassigned ? 'Unassigned' : $first['section'],
                    'is_unassigned' => $isUnassigned,
                    'rows' => $rows->sortBy('student', SORT_NATURAL | SORT_FLAG_CASE)->values(),
                ];
            })
            ->sortBy(fn (array $group): string => ($group['is_unassigned'] ? '1' : '0').'|'.$group['title'].'|'.$group['subtitle'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return $this->report('Student Masterlist by Section', $studentReport['headers'], $groups->flatMap(fn (array $group) => $group['rows'])->values())
            + ['groups' => $groups];
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
                    'time_in' => $item->checked_in_at?->format('h:i:s A') ?? '—',
                    'time_out' => $item->checked_out_at?->format('h:i:s A') ?? '—', 'source' => strtoupper($item->source),
                ];
            });

        return $this->report('Attendance Report', ['Student', 'Component', 'Section', 'Session', 'Date', 'Status', 'Time In', 'Time Out', 'Source'], $rows);
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
            ->when($filters['section_id'] ?? null, fn ($q, $value) => $q->where('id', $value))
            ->when($filters['facilitator_id'] ?? null, fn ($q, $value) => $q->where('facilitator_id', $value));
    }

    private function report(string $title, array $headers, Collection $rows): array
    {
        return compact('title', 'headers', 'rows') + ['generated_at' => now()];
    }

    private function filterSummary(array $filters): string
    {
        $summary = [];

        if ($filters['academic_year'] ?? null) {
            $summary[] = 'Academic year: '.$filters['academic_year'];
        }
        if ($filters['semester'] ?? null) {
            $summary[] = 'Semester: '.(NstpSection::SEMESTERS[$filters['semester']] ?? str($filters['semester'])->headline());
        }
        if ($filters['component_id'] ?? null) {
            $summary[] = 'Component: '.(NstpComponent::find($filters['component_id'])?->code ?? 'Unavailable');
        }
        if ($filters['section_id'] ?? null) {
            $summary[] = 'Section: '.(NstpSection::find($filters['section_id'])?->code ?? 'Unavailable');
        }
        if ($filters['date_from'] ?? null) {
            $summary[] = 'From: '.$filters['date_from'];
        }
        if ($filters['date_to'] ?? null) {
            $summary[] = 'To: '.$filters['date_to'];
        }
        if ($filters['facilitator_id'] ?? null) {
            $summary[] = 'Scope: Assigned sections';
        }

        return $summary === [] ? 'All records' : implode(' · ', $summary);
    }

    private function attendanceRate(?int $componentId = null, ?int $facilitatorId = null): float
    {
        $records = AttendanceRecord::query()
            ->when($componentId !== null, fn ($query) => $query->whereHas('attendanceSession.section', fn ($section) => $section->where('component_id', $componentId)))
            ->when($facilitatorId !== null, fn ($query) => $query->whereHas('attendanceSession.section', fn ($section) => $section->where('facilitator_id', $facilitatorId)));
        $total = (clone $records)->count();

        return $total ? round(((clone $records)->whereIn('status', ['present', 'late'])->count() / $total) * 100, 1) : 0;
    }

    private function attendanceTrend(array $filters): Collection
    {
        return AttendanceRecord::query()
            ->select(['id', 'attendance_session_id', 'status'])
            ->with('attendanceSession:id,section_id,starts_at')
            ->whereHas('attendanceSession.section', fn ($query) => $this->applySectionFilters($query, $filters))
            ->whereHas('attendanceSession', function ($query) use ($filters): void {
                $query->when($filters['date_from'] ?? null, fn ($attendance, $date) => $attendance->whereDate('starts_at', '>=', $date))
                    ->when($filters['date_to'] ?? null, fn ($attendance, $date) => $attendance->whereDate('starts_at', '<=', $date));
            })
            ->get()
            ->filter(fn (AttendanceRecord $record): bool => $record->attendanceSession?->starts_at !== null)
            ->groupBy(fn (AttendanceRecord $record): string => $record->attendanceSession->starts_at->toDateString())
            ->sortKeys()
            ->take(-12)
            ->map(function (Collection $records, string $date): array {
                $attended = $records->whereIn('status', ['present', 'late'])->count();
                $total = $records->count();

                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('M j'),
                    'rate' => $total > 0 ? round(($attended / $total) * 100, 1) : 0,
                    'attended' => $attended,
                    'total' => $total,
                ];
            })
            ->values();
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
            'bottom' => $bottom,
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

    private function enrollmentBreakdown(array $filters, Collection $components): Collection
    {
        $totals = NstpEnrollment::query()
            ->where('status', 'enrolled')
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->where('academic_year', $year))
            ->when($filters['semester'] ?? null, fn ($query, $semester) => $query->where('semester', $semester))
            ->when($filters['component_id'] ?? null, fn ($query, $componentId) => $query->where('component_id', $componentId))
            ->when($filters['section_id'] ?? null, fn ($query, $sectionId) => $query->where('section_id', $sectionId))
            ->when($filters['facilitator_id'] ?? null, fn ($query, $facilitatorId) => $query->whereHas('section', fn ($section) => $section->where('facilitator_id', $facilitatorId)))
            ->selectRaw('component_id, COUNT(DISTINCT student_id) as enrollment_count')
            ->groupBy('component_id')
            ->pluck('enrollment_count', 'component_id');

        $selectedComponentId = isset($filters['component_id']) ? (int) $filters['component_id'] : null;

        if (($filters['section_id'] ?? null) && $selectedComponentId === null) {
            $selectedComponentId = NstpSection::whereKey($filters['section_id'])->value('component_id');
        }

        return $components
            ->when($selectedComponentId !== null, fn (Collection $items) => $items->where('id', $selectedComponentId))
            ->map(fn (NstpComponent $component): array => [
                'code' => $component->code,
                'name' => $component->name,
                'count' => (int) ($totals[$component->id] ?? 0),
            ])
            ->values();
    }

    private function routePrefix(Request $request): string
    {
        return match (true) {
            $request->user()->isCoordinator() => 'coordinator',
            $request->user()->isFacilitator() => 'facilitator',
            $request->user()->isNstpAdmin() => 'nstp_admin',
            default => 'admin',
        };
    }

    private function availableReportTypes(Request $request): array
    {
        $types = self::TYPES;

        if ($request->user()->isCoordinator() || $request->user()->isFacilitator()) {
            unset($types['students_by_section']);
        }

        return $types;
    }
}
