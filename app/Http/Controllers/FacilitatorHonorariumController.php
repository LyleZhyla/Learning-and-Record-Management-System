<?php

namespace App\Http\Controllers;

use App\Models\FacilitatorAccomplishmentReport;
use App\Models\FacilitatorHonorarium;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\PortalAccessService;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FacilitatorHonorariumController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $records = $this->visibleRecords($user)
            ->with(['facilitator', 'component', 'requester', 'preparer'])
            ->latest('requested_at')->get();

        $selectedMonth = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ])['month'] ?? now()->format('Y-m');
        $reportMonth = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $assignments = collect();
        $accomplishmentReports = collect();
        $reportMetrics = null;

        if ($user->isFacilitator()) {
            $assignments = $user->facilitatedSections()
                ->with('component')
                ->where('status', 'active')
                ->orderByDesc('academic_year')
                ->orderBy('semester')
                ->get()
                ->unique(fn (NstpSection $section): string => implode('|', [$section->component_id, $section->academic_year, $section->semester]))
                ->values();
            $accomplishmentReports = FacilitatorAccomplishmentReport::query()
                ->with(['component', 'reviewer'])
                ->where('facilitator_id', $user->id)
                ->latest('report_month')
                ->latest('submitted_at')
                ->get();
        } elseif ($user->isNstpAdmin()) {
            $accomplishmentReports = FacilitatorAccomplishmentReport::query()
                ->with(['facilitator', 'component', 'reviewer'])
                ->whereDate('report_month', $reportMonth)
                ->orderBy(
                    User::select('last_name_sort_key')
                        ->whereColumn('users.id', 'facilitator_accomplishment_reports.facilitator_id')
                )
                ->orderByDesc('submitted_at')
                ->get();
            $eligibleFacilitators = User::query()
                ->where('role', 'facilitator')
                ->where('status', 'active')
                ->whereHas('facilitatedSections', fn (Builder $sections) => $sections->where('status', 'active'))
                ->count();
            $submittedFacilitators = $accomplishmentReports->pluck('facilitator_id')->unique()->count();
            $reportMetrics = [
                'submitted' => $accomplishmentReports->count(),
                'facilitators' => $submittedFacilitators,
                'pending_review' => $accomplishmentReports->where('status', 'submitted')->count(),
                'needs_revision' => $accomplishmentReports->where('status', 'needs_revision')->count(),
                'missing' => max(0, $eligibleFacilitators - $submittedFacilitators),
                'coverage' => $eligibleFacilitators > 0 ? round(($submittedFacilitators / $eligibleFacilitators) * 100) : 0,
            ];
        }

        return view('facilitator-honoraria.index', [
            'layout' => $this->access->layout($user),
            'routePrefix' => $this->access->routePrefix($user),
            'records' => $records,
            'statuses' => FacilitatorHonorarium::STATUSES,
            'canCreateRequests' => $user->isCoordinator() || $user->isNstpAdmin(),
            'canPrepareDocuments' => $user->isNstpAdmin(),
            'assignments' => $assignments,
            'accomplishmentReports' => $accomplishmentReports,
            'selectedMonth' => $selectedMonth,
            'reportMonth' => $reportMonth,
            'reportMetrics' => $reportMetrics,
            'metrics' => [
                'total' => $records->count(),
                'for_preparation' => $records->where('status', 'for_document_preparation')->count(),
                'prepared' => $records->where('status', 'documents_prepared')->count(),
                'forwarded' => $records->where('status', 'forwarded_to_cashier')->count(),
            ],
        ]);
    }

    public function storeAccomplishmentReport(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isFacilitator(), 403);
        $validated = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:nstp_sections,id'],
            'report_month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'accomplishment_summary' => ['required', 'string', 'max:3000'],
            'attachment' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
        ]);
        $section = NstpSection::query()
            ->whereKey($validated['assignment_id'])
            ->where('facilitator_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();
        $reportMonth = Carbon::createFromFormat('Y-m', $validated['report_month'])->startOfMonth();
        $existing = FacilitatorAccomplishmentReport::query()
            ->where('facilitator_id', $request->user()->id)
            ->where('component_id', $section->component_id)
            ->where('academic_year', $section->academic_year)
            ->where('semester', $section->semester)
            ->whereDate('report_month', $reportMonth)
            ->first();
        $oldPath = $existing?->file_path;
        $file = $validated['attachment'];
        $path = $file->store('honorarium/accomplishment-reports/'.$request->user()->id, 'local');

        try {
            FacilitatorAccomplishmentReport::updateOrCreate([
                'facilitator_id' => $request->user()->id,
                'component_id' => $section->component_id,
                'academic_year' => $section->academic_year,
                'semester' => $section->semester,
                'report_month' => $reportMonth,
            ], [
                'accomplishment_summary' => $validated['accomplishment_summary'],
                ...$this->fileData($file, $path),
                'status' => 'submitted',
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }

        return back()->with('status', ($existing ? 'Updated' : 'Submitted').' accomplishment report for '.$reportMonth->format('F Y').'.');
    }

    public function reviewAccomplishmentReport(Request $request, FacilitatorAccomplishmentReport $report): RedirectResponse
    {
        abort_unless($request->user()->isNstpAdmin(), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['reviewed', 'needs_revision'])],
            'review_notes' => ['required_if:status,needs_revision', 'nullable', 'string', 'max:2000'],
        ]);
        $report->update([
            ...$validated,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', $validated['status'] === 'reviewed'
            ? 'Monthly accomplishment report marked as reviewed.'
            : 'The report was returned to the facilitator for revision.');
    }

    public function downloadAccomplishmentReport(Request $request, FacilitatorAccomplishmentReport $report): StreamedResponse
    {
        abort_unless(
            $request->user()->isNstpAdmin()
            || ($request->user()->isFacilitator() && $report->facilitator_id === $request->user()->id),
            403
        );
        abort_unless(Storage::disk('local')->exists($report->file_path), 404);

        return Storage::disk('local')->download($report->file_path, $report->original_name, [
            'Content-Type' => $report->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function create(Request $request): View
    {
        $this->ensureCanCreate($request->user());
        $components = NstpComponent::where('is_active', true)
            ->when($request->user()->isCoordinator(), fn (Builder $query) => $query->whereKey($request->user()->nstp_component_id ?? 0))
            ->orderBy('code')->get();
        $facilitators = User::where('role', 'facilitator')->where('status', 'active')
            ->with(['facilitatedSections.component'])
            ->whereHas('facilitatedSections', fn (Builder $sections) => $sections->whereIn('component_id', $components->pluck('id')))
            ->orderByLastName()->get();

        return view('facilitator-honoraria.form', [
            'layout' => $this->access->layout($request->user()),
            'routePrefix' => $this->access->routePrefix($request->user()),
            'components' => $components,
            'facilitators' => $facilitators,
            'semesters' => NstpSection::SEMESTERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanCreate($request->user());
        $validated = $request->validate([
            'facilitator_id' => ['required', 'integer', 'exists:users,id'],
            'component_id' => ['required', 'integer', 'exists:nstp_components,id'],
            'academic_year' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', Rule::in(array_keys(NstpSection::SEMESTERS))],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'gross_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'deductions' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'request_notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $componentId = $request->user()->isCoordinator()
            ? (int) $request->user()->nstp_component_id
            : (int) $validated['component_id'];
        abort_unless($componentId > 0, 403);
        abort_unless(User::whereKey($validated['facilitator_id'])->where('role', 'facilitator')
            ->whereHas('facilitatedSections', fn (Builder $sections) => $sections->where('component_id', $componentId))->exists(), 422, 'The selected facilitator is not assigned to the selected component.');
        $gross = round((float) $validated['gross_amount'], 2);
        $deductions = round((float) ($validated['deductions'] ?? 0), 2);
        abort_if($deductions > $gross, 422, 'Deductions cannot exceed the gross honorarium.');

        $record = DB::transaction(function () use ($request, $validated, $componentId, $gross, $deductions): FacilitatorHonorarium {
            $record = FacilitatorHonorarium::create([
                ...$validated,
                'component_id' => $componentId,
                'gross_amount' => $gross,
                'deductions' => $deductions,
                'net_amount' => $gross - $deductions,
                'status' => 'for_document_preparation',
                'requested_by' => $request->user()->id,
                'requested_at' => now(),
            ]);
            $record->update(['reference_number' => sprintf('HON-%s-%05d', now()->format('Y'), $record->id)]);

            return $record;
        });

        return redirect()->route($this->access->routePrefix($request->user()).'.honoraria.index')
            ->with('status', 'Honorarium request '.$record->reference_number.' is ready for NSTP Admin document preparation.');
    }

    public function prepareDocuments(Request $request, FacilitatorHonorarium $honorarium): RedirectResponse
    {
        abort_unless($request->user()->isNstpAdmin(), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['documents_prepared', 'forwarded_to_cashier', 'returned_for_correction'])],
            'disbursement_voucher_number' => ['required_unless:status,returned_for_correction', 'nullable', 'string', 'max:100'],
            'obligation_request_number' => ['nullable', 'string', 'max:100'],
            'payroll_reference' => ['nullable', 'string', 'max:100'],
            'preparation_notes' => ['required_if:status,returned_for_correction', 'nullable', 'string', 'max:3000'],
            'cashier_forwarded_at' => ['required_if:status,forwarded_to_cashier', 'nullable', 'date', 'before_or_equal:now'],
        ]);
        $honorarium->update([
            ...$validated,
            'prepared_by' => $request->user()->id,
            'prepared_at' => now(),
            'cashier_forwarded_at' => $validated['status'] === 'forwarded_to_cashier'
                ? $validated['cashier_forwarded_at']
                : null,
        ]);

        return back()->with('status', match ($validated['status']) {
            'documents_prepared' => 'Disbursement and voucher documents prepared.',
            'forwarded_to_cashier' => 'Document package recorded as forwarded to the University Cashier.',
            default => 'Honorarium record returned for document correction.',
        });
    }

    public function voucher(Request $request, FacilitatorHonorarium $honorarium): Response
    {
        abort_unless($request->user()->isCoordinator() || $request->user()->isNstpAdmin(), 403);
        abort_unless($this->visibleRecords($request->user())->whereKey($honorarium->id)->exists(), 403);
        abort_unless(in_array($honorarium->status, ['documents_prepared', 'forwarded_to_cashier'], true), 422, 'Prepare the cashier documents before generating the voucher.');
        $honorarium->load(['facilitator.facilitatorProfile', 'component', 'requester', 'preparer']);
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('facilitator-honoraria.voucher', compact('honorarium'))->render());
        $dompdf->setPaper('a4', 'portrait');
        $dompdf->render();
        $honorarium->update([
            'voucher_generated_by' => $request->user()->id,
            'voucher_generated_at' => now(),
        ]);

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="cashier-voucher-'.$honorarium->reference_number.'.pdf"',
        ]);
    }

    private function visibleRecords(User $user): Builder
    {
        $query = FacilitatorHonorarium::query();
        if ($user->isCoordinator()) {
            $query->where('component_id', $user->nstp_component_id ?? 0);
        } elseif ($user->isFacilitator()) {
            $query->where('facilitator_id', $user->id);
        } elseif (! $user->isNstpAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function ensureCanCreate(User $user): void
    {
        abort_unless($user->isCoordinator() || $user->isNstpAdmin(), 403);
    }

    private function fileData(UploadedFile $file, string $path): array
    {
        return [
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }
}
