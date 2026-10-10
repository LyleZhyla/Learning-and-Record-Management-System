<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChedApplication;
use App\Models\NstpSection;
use App\Models\SystemSetting;
use App\Services\ChedSemestralReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChedApplicationController extends Controller
{
    public function __construct(private ChedSemestralReportService $reports) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $academicYear = $request->string('academic_year')->toString();
        $applications = ChedApplication::with('creator')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($academicYear, fn ($query) => $query->where('academic_year', $academicYear))
            ->latest()->paginate($this->perPage())->withQueryString();

        return view('admin.ched-applications.index', [
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
            'applications' => $applications,
            'statuses' => ChedApplication::STATUSES,
            'academicYears' => ChedApplication::distinct()->orderByDesc('academic_year')->pluck('academic_year'),
            'defaultAcademicYear' => SystemSetting::studentRegistrationAcademicYear(),
            'defaultSemester' => SystemSetting::studentRegistrationSemester(),
            'selectedStatus' => $status,
            'selectedAcademicYear' => $academicYear,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/', function (string $attribute, mixed $value, \Closure $fail): void {
                [$start, $end] = array_map('intval', explode('-', (string) $value));
                if ($end !== $start + 1) {
                    $fail('The academic year must contain consecutive years.');
                }
            }],
            'semester' => ['required', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'submission_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $application = DB::transaction(function () use ($request, $validated): ChedApplication {
            $studentCount = $this->reports->report([
                'academic_year' => $validated['academic_year'],
                'semester' => $validated['semester'],
                'component_id' => null,
                'section_id' => null,
            ])['rows']->count();
            $application = ChedApplication::create([
                ...$validated,
                'student_count' => $studentCount,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
            $application->update(['reference_number' => sprintf('CHED-%s-%05d', substr($application->academic_year, 0, 4), $application->id)]);
            $application->histories()->create([
                'to_status' => 'draft',
                'notes' => 'Application tracking record created. No submission was sent by the system.',
                'changed_by' => $request->user()->id,
                'occurred_at' => now(),
            ]);

            return $application;
        });

        return redirect()->route($this->routePrefix($request).'.ched-applications.show', $application)
            ->with('status', 'CHED application record created. Download the workbook, email it manually, then record the submission status here.');
    }

    public function show(Request $request, ChedApplication $chedApplication): View
    {
        $chedApplication->load(['creator', 'updater', 'histories.changedBy']);

        return view('admin.ched-applications.show', [
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
            'application' => $chedApplication,
            'nextStatuses' => $chedApplication->availableNextStatuses(),
        ]);
    }

    public function updateStatus(Request $request, ChedApplication $chedApplication)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(ChedApplication::STATUSES))],
            'status_date' => ['required', 'date'],
            'submission_email' => ['nullable', 'email', 'max:255'],
            'ched_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['required', 'string', 'max:3000'],
        ]);
        if (! array_key_exists($validated['status'], $chedApplication->availableNextStatuses())) {
            throw ValidationException::withMessages(['status' => 'That status is not a valid next step for this application.']);
        }
        if ($validated['status'] === 'emailed' && blank($validated['submission_email'] ?? $chedApplication->submission_email)) {
            throw ValidationException::withMessages(['submission_email' => 'Record the CHED email address used for the manual submission.']);
        }

        DB::transaction(function () use ($request, $validated, $chedApplication): void {
            $fromStatus = $chedApplication->status;
            $occurredAt = $validated['status_date'];
            $updates = [
                'status' => $validated['status'],
                'submission_email' => ($validated['submission_email'] ?? null) ?: $chedApplication->submission_email,
                'ched_reference' => ($validated['ched_reference'] ?? null) ?: $chedApplication->ched_reference,
                'notes' => $validated['notes'],
                'updated_by' => $request->user()->id,
            ];
            if ($validated['status'] === 'workbook_prepared') {
                $updates['prepared_at'] = $occurredAt;
            }
            if ($validated['status'] === 'emailed') {
                $updates['submitted_at'] = $occurredAt;
            }
            if ($validated['status'] === 'acknowledged') {
                $updates['acknowledged_at'] = $occurredAt;
            }
            if ($validated['status'] === 'serials_released') {
                $updates['serials_released_at'] = $occurredAt;
            }
            $chedApplication->update($updates);
            $chedApplication->histories()->create([
                'from_status' => $fromStatus,
                'to_status' => $validated['status'],
                'notes' => $validated['notes'],
                'changed_by' => $request->user()->id,
                'occurred_at' => $occurredAt,
            ]);
        });

        return back()->with('status', 'CHED application status updated.');
    }

    public function workbook(Request $request, ChedApplication $chedApplication): StreamedResponse
    {
        $spreadsheet = $this->reports->createWorkbook([
            'academic_year' => $chedApplication->academic_year,
            'semester' => $chedApplication->semester,
            'component_id' => null,
            'section_id' => null,
        ]);

        DB::transaction(function () use ($request, $chedApplication): void {
            $updates = ['last_workbook_downloaded_at' => now(), 'updated_by' => $request->user()->id];
            if (in_array($chedApplication->status, ['draft', 'returned'], true)) {
                $fromStatus = $chedApplication->status;
                $updates += ['status' => 'workbook_prepared', 'prepared_at' => now()];
                $chedApplication->histories()->create([
                    'from_status' => $fromStatus,
                    'to_status' => 'workbook_prepared',
                    'notes' => 'Official CHED workbook downloaded for manual email submission.',
                    'changed_by' => $request->user()->id,
                    'occurred_at' => now(),
                ]);
            }
            $chedApplication->update($updates);
        });

        $filename = str($chedApplication->reference_number)->lower().'-'.$chedApplication->academic_year.'-'.$chedApplication->semester.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isNstpAdmin() ? 'nstp_admin' : 'admin';
    }

    private function layout(Request $request): string
    {
        return $request->user()->isNstpAdmin() ? 'layouts.nstp-admin' : 'layouts.admin';
    }
}
