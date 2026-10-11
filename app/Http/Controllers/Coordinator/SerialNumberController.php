<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\NstpSerialNumberRelease;
use App\Models\NstpStudentSerialNumber;
use App\Models\SystemSetting;
use App\Services\GradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SerialNumberController extends Controller
{
    public function __construct(private GradeService $grades) {}

    public function index(Request $request): View
    {
        $isProgramAdministrator = $this->isProgramAdministrator($request);
        $components = NstpComponent::query()->where('is_active', true)->orderBy('code')->get();
        $selectedComponentId = $isProgramAdministrator
            ? $request->integer('component_id')
            : (int) ($request->user()->nstp_component_id ?? 0);
        $selectedAcademicYear = $request->string('academic_year')->toString()
            ?: SystemSetting::studentRegistrationAcademicYear();

        $releases = NstpSerialNumberRelease::with(['component', 'uploader'])
            ->withCount('serialNumbers')
            ->when($selectedComponentId > 0, fn ($query) => $query->where('component_id', $selectedComponentId))
            ->where('academic_year', $selectedAcademicYear)
            ->latest('received_at')->latest('id')->paginate($this->perPage())->withQueryString();
        $yearReleases = NstpSerialNumberRelease::with(['component', 'serialNumbers'])
            ->when($selectedComponentId > 0, fn ($query) => $query->where('component_id', $selectedComponentId))
            ->where('academic_year', $selectedAcademicYear)
            ->latest('received_at')->latest('id')->get();
        $coveredEnrollments = $yearReleases->flatMap(function (NstpSerialNumberRelease $release): Collection {
            return $this->qualifiedGraduates($release)->map(fn (NstpEnrollment $enrollment): array => [
                'release' => $release,
                'enrollment' => $enrollment,
            ]);
        })->unique(fn (array $row): int => $row['enrollment']->id)->values();
        $serialRecords = NstpStudentSerialNumber::query()
            ->with(['release', 'encoder'])
            ->whereIn('enrollment_id', $coveredEnrollments->pluck('enrollment.id'))
            ->get()->keyBy('enrollment_id');
        $studentStatus = $request->string('student_status')->toString();
        $search = trim($request->string('search')->toString());
        $studentRows = $coveredEnrollments
            ->map(fn (array $row): array => $row + ['serial' => $serialRecords->get($row['enrollment']->id)])
            ->when($studentStatus === 'encoded', fn (Collection $rows) => $rows->filter(fn (array $row): bool => $row['serial'] !== null))
            ->when($studentStatus === 'missing', fn (Collection $rows) => $rows->filter(fn (array $row): bool => $row['serial'] === null))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(function (array $row) use ($search): bool {
                $student = $row['enrollment']->student;
                $haystack = implode(' ', [$student->name, $student->studentRecordName(), $student->studentProfile?->student_number, $student->email, $row['serial']?->serial_number]);

                return str_contains(mb_strtolower($haystack), mb_strtolower($search));
            }))
            ->sortBy(fn (array $row): string => $row['enrollment']->student->studentRecordName(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $allEligibleCount = $coveredEnrollments->count();
        $encodedCount = $coveredEnrollments->filter(fn (array $row): bool => $serialRecords->has($row['enrollment']->id))->count();

        return view('coordinator.serial-numbers.index', [
            'releases' => $releases,
            'components' => $components,
            'component' => $isProgramAdministrator
                ? $components->firstWhere('id', $selectedComponentId)
                : $request->user()->nstpComponent,
            'selectedComponentId' => $selectedComponentId,
            'selectedAcademicYear' => $selectedAcademicYear,
            'academicYears' => NstpSerialNumberRelease::query()->distinct()->orderByDesc('academic_year')->pluck('academic_year')
                ->push(SystemSetting::studentRegistrationAcademicYear())->unique()->sortDesc()->values(),
            'studentStatus' => $studentStatus,
            'search' => $search,
            'studentRows' => $this->paginateCollection($studentRows, $request, 'students_page'),
            'serialMetrics' => [
                'eligible' => $allEligibleCount,
                'encoded' => $encodedCount,
                'missing' => max(0, $allEligibleCount - $encodedCount),
            ],
            'isProgramAdministrator' => $isProgramAdministrator,
            'semesters' => NstpSection::SEMESTERS,
            ...$this->viewContext($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isProgramAdministrator = $this->isProgramAdministrator($request);
        $componentId = $isProgramAdministrator
            ? $request->integer('component_id')
            : (int) ($request->user()->nstp_component_id ?? 0);
        abort_if($componentId === 0, 422, $isProgramAdministrator
            ? 'Select an NSTP component for this serial-number batch.'
            : 'A coordinator component assignment is required.');

        $validated = $request->validate([
            'component_id' => $isProgramAdministrator
                ? ['required', 'integer', Rule::exists('nstp_components', 'id')->where('is_active', true)]
                : ['nullable'],
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/', function (string $attribute, mixed $value, \Closure $fail): void {
                [$start, $end] = array_map('intval', explode('-', (string) $value));
                if ($end !== $start + 1) {
                    $fail('The academic year must contain consecutive years.');
                }
            }],
            'semester' => ['required', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'received_at' => ['required', 'date', 'before_or_equal:today'],
            'source_file' => ['required', 'file', 'mimes:pdf,xlsx,xls,csv,jpg,jpeg,png', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $file = $request->file('source_file');
        $path = $file->store('nstp-serial-number-releases', 'local');

        try {
            $release = NstpSerialNumberRelease::create([
                ...collect($validated)->except(['source_file', 'component_id'])->all(),
                'component_id' => $componentId,
                'source_file_path' => $path,
                'source_file_original_name' => $file->getClientOriginalName(),
                'source_file_mime_type' => $file->getMimeType(),
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return redirect()->route($this->routePrefix($request).'.serial-numbers.show', $release)
            ->with('status', 'Official serial-number file uploaded. You can now encode the CHED-provided numbers for qualified graduates.');
    }

    public function show(Request $request, NstpSerialNumberRelease $serialNumberRelease): View
    {
        return $this->encode($request, $serialNumberRelease);
    }

    public function encode(Request $request, NstpSerialNumberRelease $serialNumberRelease): View
    {
        $this->authorizeRelease($request, $serialNumberRelease);
        $serialNumberRelease->load(['component', 'uploader', 'serialNumbers.student']);
        $allGraduates = $this->qualifiedGraduates($serialNumberRelease);
        $encoded = NstpStudentSerialNumber::query()
            ->whereIn('enrollment_id', $allGraduates->pluck('id'))
            ->get()->keyBy('enrollment_id');
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());
        $graduates = $allGraduates
            ->when($status === 'encoded', fn (Collection $rows) => $rows->filter(fn (NstpEnrollment $enrollment): bool => $encoded->has($enrollment->id)))
            ->when($status === 'missing', fn (Collection $rows) => $rows->reject(fn (NstpEnrollment $enrollment): bool => $encoded->has($enrollment->id)))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(function (NstpEnrollment $enrollment) use ($search, $encoded): bool {
                $student = $enrollment->student;
                $haystack = implode(' ', [$student->name, $student->studentRecordName(), $student->studentProfile?->student_number, $student->email, $encoded->get($enrollment->id)?->serial_number]);

                return str_contains(mb_strtolower($haystack), mb_strtolower($search));
            }))
            ->values();

        return view('coordinator.serial-numbers.show', [
            'serialNumberRelease' => $serialNumberRelease,
            'graduates' => $this->paginateCollection($graduates, $request),
            'encoded' => $encoded,
            'encodedCount' => $encoded->count(),
            'graduateCount' => $allGraduates->count(),
            'selectedStatus' => $status,
            'search' => $search,
            ...$this->viewContext($request),
        ]);
    }

    public function storeSerial(Request $request, NstpSerialNumberRelease $serialNumberRelease, NstpEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeRelease($request, $serialNumberRelease);
        abort_unless($this->enrollmentBelongsToRelease($enrollment, $serialNumberRelease), 404);
        abort_unless($this->isQualifiedGraduate($enrollment), 422, 'Serial numbers can only be encoded for fully graded, passing students.');

        $existing = NstpStudentSerialNumber::where('enrollment_id', $enrollment->id)->first();
        $request->merge(['serial_number' => strtoupper(trim((string) $request->input('serial_number')))]);
        $validated = $request->validate([
            'serial_number' => [
                'required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/\. ]+$/',
                Rule::unique('nstp_student_serial_numbers', 'serial_number')->ignore($existing?->id),
            ],
        ]);

        NstpStudentSerialNumber::updateOrCreate(
            ['enrollment_id' => $enrollment->id],
            [
                'release_id' => $serialNumberRelease->id,
                'student_id' => $enrollment->student_id,
                'serial_number' => $validated['serial_number'],
                'encoded_by' => $request->user()->id,
            ]
        );

        return back()->with('status', 'CHED serial number saved for '.$enrollment->student->name.'.');
    }

    public function download(Request $request, NstpSerialNumberRelease $serialNumberRelease): StreamedResponse
    {
        $this->authorizeRelease($request, $serialNumberRelease);
        abort_unless(Storage::disk('local')->exists($serialNumberRelease->source_file_path), 404);

        return Storage::disk('local')->download(
            $serialNumberRelease->source_file_path,
            $serialNumberRelease->source_file_original_name,
            ['Content-Type' => $serialNumberRelease->source_file_mime_type ?: 'application/octet-stream']
        );
    }

    private function authorizeRelease(Request $request, NstpSerialNumberRelease $release): void
    {
        if ($this->isProgramAdministrator($request)) {
            return;
        }

        abort_unless((int) $request->user()->nstp_component_id === (int) $release->component_id, 403);
    }

    private function isProgramAdministrator(Request $request): bool
    {
        return $request->user()->isSuperAdmin() || $request->user()->isNstpAdmin();
    }

    /** @return array{layout: string, routePrefix: string} */
    private function viewContext(Request $request): array
    {
        $routePrefix = $this->routePrefix($request);

        return [
            'layout' => match ($routePrefix) {
                'admin' => 'layouts.admin',
                'nstp_admin' => 'layouts.nstp-admin',
                default => 'layouts.coordinator',
            },
            'routePrefix' => $routePrefix,
        ];
    }

    private function routePrefix(Request $request): string
    {
        return match (true) {
            $request->user()->isSuperAdmin() => 'admin',
            $request->user()->isNstpAdmin() => 'nstp_admin',
            default => 'coordinator',
        };
    }

    /** @return Collection<int, NstpEnrollment> */
    private function qualifiedGraduates(NstpSerialNumberRelease $release): Collection
    {
        return NstpEnrollment::with(['student.studentProfile', 'component', 'section'])
            ->where('component_id', $release->component_id)
            ->where('academic_year', $release->academic_year)
            ->where('semester', $release->semester)
            ->where('status', 'enrolled')
            ->whereNotNull('section_id')
            ->get()
            ->filter(fn (NstpEnrollment $enrollment) => $this->isQualifiedGraduate($enrollment))
            ->sortBy(fn (NstpEnrollment $enrollment) => $enrollment->student->studentRecordName(), SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function paginateCollection(Collection $items, Request $request, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $perPage = $this->perPage(20);

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $pageName],
        );
    }

    private function enrollmentBelongsToRelease(NstpEnrollment $enrollment, NstpSerialNumberRelease $release): bool
    {
        return (int) $enrollment->component_id === (int) $release->component_id
            && $enrollment->academic_year === $release->academic_year
            && $enrollment->semester === $release->semester
            && $enrollment->status === 'enrolled';
    }

    private function isQualifiedGraduate(NstpEnrollment $enrollment): bool
    {
        if (! $enrollment->section_id) {
            return false;
        }

        $summary = $this->grades->summary($enrollment->student, $enrollment->section_id);

        return $summary['total_count'] > 0
            && $summary['graded_count'] === $summary['total_count']
            && $summary['percentage'] !== null
            && $summary['percentage'] >= (float) $summary['settings']->passing_percentage;
    }
}
