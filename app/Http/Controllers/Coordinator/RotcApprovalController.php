<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\NstpEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RotcApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureReviewerCanManageRotcApprovals($request);

        $pendingRequests = NstpEnrollment::query()
            ->with(['student', 'component'])
            ->whereHas('component', fn ($query) => $query->where('code', 'ROTC'))
            ->whereIn('rotc_category', ['MS-31', 'MS-41'])
            ->where('rotc_approval_status', 'pending')
            ->where('status', 'pending_approval')
            ->oldest()
            ->paginate($this->perPage())
            ->withQueryString();

        return view('coordinator.rotc-approvals.index', [
            'pendingRequests' => $pendingRequests,
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function showProof(Request $request, NstpEnrollment $enrollment): View
    {
        $this->ensureReviewerCanManageRotcApprovals($request);
        $this->ensurePendingRotcRequest($enrollment);
        abort_unless($enrollment->rotc_proof_path && Storage::disk('local')->exists($enrollment->rotc_proof_path), 404);

        return view('coordinator.rotc-approvals.proof', [
            'enrollment' => $enrollment,
            'layout' => $this->layout($request),
            'routePrefix' => $this->routePrefix($request),
        ]);
    }

    public function streamProof(Request $request, NstpEnrollment $enrollment): StreamedResponse
    {
        $this->ensureReviewerCanManageRotcApprovals($request);
        $this->ensurePendingRotcRequest($enrollment);
        abort_unless($enrollment->rotc_proof_path && Storage::disk('local')->exists($enrollment->rotc_proof_path), 404);

        return Storage::disk('local')->response(
            $enrollment->rotc_proof_path,
            $enrollment->rotc_proof_original_name ?? 'ms1-proof',
            ['Content-Disposition' => 'inline'],
        );
    }

    public function downloadProof(Request $request, NstpEnrollment $enrollment): StreamedResponse
    {
        $this->ensureReviewerCanManageRotcApprovals($request);
        $this->ensurePendingRotcRequest($enrollment);
        abort_unless($enrollment->rotc_proof_path && Storage::disk('local')->exists($enrollment->rotc_proof_path), 404);

        return Storage::disk('local')->download(
            $enrollment->rotc_proof_path,
            $enrollment->rotc_proof_original_name ?? 'ms1-proof',
        );
    }

    public function approve(Request $request, NstpEnrollment $enrollment): RedirectResponse
    {
        $this->ensureReviewerCanManageRotcApprovals($request);
        $this->ensurePendingRotcRequest($enrollment);
        abort_unless($enrollment->rotc_proof_path && Storage::disk('local')->exists($enrollment->rotc_proof_path), 422, 'The MS-1 proof file is missing.');

        $enrollment->update([
            'rotc_approval_status' => 'approved',
            'rotc_approved_by' => $request->user()->id,
            'rotc_approved_at' => now(),
            'status' => 'enrolled',
        ]);

        return back()->with('status', $enrollment->student->name.' was approved for '.$enrollment->rotc_category.'.');
    }

    private function ensureReviewerCanManageRotcApprovals(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->isSuperAdmin()
            || $user->isNstpAdmin()
            || ($user->isCoordinator() && $user->nstpComponent?->code === 'ROTC'),
            403,
        );
    }

    private function routePrefix(Request $request): string
    {
        return match (true) {
            $request->user()->isSuperAdmin() => 'admin',
            $request->user()->isNstpAdmin() => 'nstp_admin',
            default => 'coordinator',
        };
    }

    private function layout(Request $request): string
    {
        return match ($this->routePrefix($request)) {
            'admin' => 'layouts.admin',
            'nstp_admin' => 'layouts.nstp-admin',
            default => 'layouts.coordinator',
        };
    }

    private function ensurePendingRotcRequest(NstpEnrollment $enrollment): void
    {
        $enrollment->loadMissing(['component', 'student']);
        abort_unless(
            $enrollment->component?->code === 'ROTC'
            && in_array($enrollment->rotc_category, ['MS-31', 'MS-41'], true)
            && $enrollment->rotc_approval_status === 'pending'
            && $enrollment->status === 'pending_approval',
            404,
        );
    }
}
