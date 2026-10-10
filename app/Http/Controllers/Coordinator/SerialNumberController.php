<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\NstpSerialNumberRelease;
use App\Models\NstpStudentSerialNumber;
use App\Services\GradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $releases = NstpSerialNumberRelease::with(['component', 'uploader'])
            ->withCount('serialNumbers')
            ->when($selectedComponentId > 0, fn ($query) => $query->where('component_id', $selectedComponentId))
            ->latest('received_at')->latest('id')->paginate(15)->withQueryString();

        return view('coordinator.serial-numbers.index', [
            'releases' => $releases,
            'components' => $components,
            'component' => $isProgramAdministrator
                ? $components->firstWhere('id', $selectedComponentId)
                : $request->user()->nstpComponent,
            'selectedComponentId' => $selectedComponentId,
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
        $this->authorizeRelease($request, $serialNumberRelease);
        $serialNumberRelease->load(['component', 'uploader', 'serialNumbers.student']);
        $graduates = $this->qualifiedGraduates($serialNumberRelease);
        $encoded = $serialNumberRelease->serialNumbers->keyBy('enrollment_id');

        return view('coordinator.serial-numbers.show', [
            'serialNumberRelease' => $serialNumberRelease,
            'graduates' => $graduates,
            'encoded' => $encoded,
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
            ->sortBy(fn (NstpEnrollment $enrollment) => $enrollment->student->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
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
