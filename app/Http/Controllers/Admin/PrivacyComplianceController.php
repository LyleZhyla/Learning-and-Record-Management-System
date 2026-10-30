<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRestorationDrill;
use App\Models\DataPrivacyRequest;
use App\Models\PrivacyConsent;
use App\Models\PrivacyIncident;
use App\Models\PrivacyNotice;
use App\Models\RetentionPolicy;
use App\Models\SensitiveFieldAccessLog;
use App\Services\AnonymizedAnalyticsService;
use App\Services\DatabaseBackupService;
use App\Services\InstitutionContext;
use App\Services\PrivacyDataExportService;
use App\Services\RetentionPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PrivacyComplianceController extends Controller
{
    public function index(AnonymizedAnalyticsService $analytics, DatabaseBackupService $backups): View
    {
        return view('admin.privacy-compliance.index', [
            'notices' => PrivacyNotice::query()->latest('effective_at')->get(),
            'consentCount' => PrivacyConsent::query()->count(),
            'policies' => RetentionPolicy::query()->orderBy('record_type')->get(),
            'requests' => DataPrivacyRequest::query()->latest()->limit(25)->get(),
            'accessLogs' => SensitiveFieldAccessLog::query()->latest('accessed_at')->limit(25)->get(),
            'incidents' => PrivacyIncident::query()->latest()->limit(25)->get(),
            'drills' => BackupRestorationDrill::query()->latest('started_at')->limit(15)->get(),
            'analytics' => $analytics->summary(),
            'archives' => auth()->user()->isSystemAdmin() ? $backups->archives() : collect(),
        ]);
    }

    public function storeNotice(Request $request, InstitutionContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:40', Rule::unique('privacy_notices')->where('institution_id', $context->id())],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'max:20000'],
            'effective_at' => ['required', 'date'],
        ]);
        DB::transaction(function () use ($validated): void {
            PrivacyNotice::query()->update(['is_active' => false]);
            PrivacyNotice::create([...$validated, 'is_active' => true, 'published_by' => auth()->id()]);
        });

        return back()->with('status', 'Privacy notice version '.$validated['version'].' published and activated.');
    }

    public function updatePolicy(Request $request, RetentionPolicy $retentionPolicy): RedirectResponse
    {
        $validated = $request->validate([
            'retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'disposition' => ['required', Rule::in(RetentionPolicy::DISPOSITIONS)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $allowed = match ($retentionPolicy->record_type) {
            'audit_logs' => ['archive', 'delete'],
            'privacy_consents' => ['anonymize'],
            default => ['delete'],
        };
        abort_unless(in_array($validated['disposition'], $allowed, true), 422);
        $retentionPolicy->update([
            ...$validated,
            'is_active' => $request->boolean('is_active'),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Institution retention policy updated.');
    }

    public function enforcePolicy(RetentionPolicy $retentionPolicy, RetentionPolicyService $service): RedirectResponse
    {
        abort_unless($retentionPolicy->is_active, 422);
        $affected = $service->enforce($retentionPolicy);

        return back()->with('status', "Retention policy enforced. {$affected} record(s) affected.");
    }

    public function updateRequest(Request $request, DataPrivacyRequest $privacyRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(DataPrivacyRequest::STATUSES)],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $privacyRequest->update([
            ...$validated,
            'handled_by' => $request->user()->id,
            'completed_at' => in_array($validated['status'], ['completed', 'rejected'], true) ? now() : null,
        ]);

        return back()->with('status', 'Privacy request updated.');
    }

    public function exportRequest(DataPrivacyRequest $privacyRequest, PrivacyDataExportService $exports): JsonResponse
    {
        abort_unless($privacyRequest->request_type === 'export', 404);

        return response()->json($exports->build($privacyRequest), 200, [
            'Content-Disposition' => 'attachment; filename="'.$privacyRequest->reference_number.'.json"',
            'Cache-Control' => 'no-store, private',
        ], JSON_PRETTY_PRINT);
    }

    public function updateIncident(Request $request, PrivacyIncident $privacyIncident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(PrivacyIncident::STATUSES)],
            'containment_actions' => ['nullable', 'string', 'max:5000'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $privacyIncident->update([
            ...$validated,
            'handled_by' => $request->user()->id,
            'resolved_at' => in_array($validated['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);

        return back()->with('status', 'Incident response record updated.');
    }

    public function runDrill(Request $request, DatabaseBackupService $backups, InstitutionContext $context): RedirectResponse
    {
        abort_unless($request->user()->isSystemAdmin(), 403);
        $validated = $request->validate(['archive_name' => ['required', 'string', 'max:255']]);
        $drill = BackupRestorationDrill::create([
            'institution_id' => $context->id(),
            'conducted_by' => $request->user()->id,
            'archive_name' => $validated['archive_name'],
            'archive_checksum' => str_repeat('0', 64),
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $backups->validateRestorationDrill($validated['archive_name']);
            $drill->update([
                'archive_checksum' => $result['checksum'],
                'tables_detected' => $result['tables'],
                'findings' => $result['findings'],
                'status' => 'passed',
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $drill->update(['status' => 'failed', 'findings' => $exception->getMessage(), 'completed_at' => now()]);

            return back()->withErrors(['archive_name' => 'Restoration drill failed: '.$exception->getMessage()]);
        }

        return back()->with('status', 'Backup restoration drill passed without changing the live database.');
    }
}
