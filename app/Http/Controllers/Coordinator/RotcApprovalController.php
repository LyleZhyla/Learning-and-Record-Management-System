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
        $this->ensureRotcCoordinator($request);

        $pendingRequests = NstpEnrollment::query()
            ->with(['student', 'component'])
            ->whereHas('component', fn ($query) => $query->where('code', 'ROTC'))
            ->whereIn('rotc_category', ['MS-31', 'MS-41'])
            ->where('rotc_approval_status', 'pending')
            ->where('status', 'pending_approval')
            ->oldest()
            ->paginate(15);

        return view('coordinator.rotc-approvals.index', compact('pendingRequests'));
    }

    public function downloadProof(Request $request, NstpEnrollment $enrollment): StreamedResponse
    {
        $this->ensureRotcCoordinator($request);
        $this->ensurePendingRotcRequest($enrollment);
        abort_unless($enrollment->rotc_proof_path && Storage::disk('local')->exists($enrollment->rotc_proof_path), 404);

        return Storage::disk('local')->download(
            $enrollment->rotc_proof_path,
            $enrollment->rotc_proof_original_name ?? 'ms1-proof',
        );
    }

    public function approve(Request $request, NstpEnrollment $enrollment): RedirectResponse
    {
        $this->ensureRotcCoordinator($request);
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

    private function ensureRotcCoordinator(Request $request): void
    {
        abort_unless($request->user()->isCoordinator() && $request->user()->nstpComponent?->code === 'ROTC', 403);
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
