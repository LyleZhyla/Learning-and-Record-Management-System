<?php

namespace App\Http\Controllers\NstpAdmin;

use App\Http\Controllers\Controller;
use App\Models\NstpServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'request_type' => ['nullable', Rule::in(array_keys(NstpServiceRequest::REQUEST_TYPES))],
            'status' => ['nullable', Rule::in(array_keys(NstpServiceRequest::STATUSES))],
        ]);
        $search = trim($validated['search'] ?? '');
        $requests = NstpServiceRequest::query()
            ->with('processor')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $match) use ($search): void {
                    $match->where('reference_code', 'like', "%{$search}%")
                        ->orWhere('requester_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%");
                });
            })
            ->when($validated['request_type'] ?? null, fn (Builder $query, string $type) => $query->where('request_type', $type))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate($this->perPage(15))
            ->withQueryString();
        $statusCounts = NstpServiceRequest::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');

        return view('nstp_admin.service-requests.index', [
            'requests' => $requests,
            'requestTypes' => NstpServiceRequest::REQUEST_TYPES,
            'statuses' => NstpServiceRequest::STATUSES,
            'filters' => $validated,
            'metrics' => [
                'total' => (int) $statusCounts->sum(),
                'new' => (int) ($statusCounts['submitted'] ?? 0),
                'in_progress' => (int) collect($statusCounts)->only(['under_review', 'needs_information', 'approved'])->sum(),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
            ],
        ]);
    }

    public function show(NstpServiceRequest $serviceRequest): View
    {
        return view('nstp_admin.service-requests.show', [
            'serviceRequest' => $serviceRequest->load('processor'),
            'statuses' => NstpServiceRequest::STATUSES,
        ]);
    }

    public function update(Request $request, NstpServiceRequest $serviceRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(NstpServiceRequest::STATUSES))],
            'status_note' => [Rule::requiredIf(in_array($request->input('status'), ['needs_information', 'declined'], true)), 'nullable', 'string', 'max:3000'],
        ]);
        $serviceRequest->update([
            ...$validated,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        return back()->with('status', 'Application status updated to '.$serviceRequest->statusLabel().'.');
    }

    public function download(NstpServiceRequest $serviceRequest): StreamedResponse
    {
        abort_unless($serviceRequest->attachment_path && Storage::disk('local')->exists($serviceRequest->attachment_path), 404);

        return Storage::disk('local')->download(
            $serviceRequest->attachment_path,
            $serviceRequest->attachment_original_name ?: 'supporting-document',
            ['Content-Type' => $serviceRequest->attachment_mime_type ?: 'application/octet-stream']
        );
    }
}
