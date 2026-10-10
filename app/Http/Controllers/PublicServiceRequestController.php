<?php

namespace App\Http\Controllers;

use App\Models\NstpServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PublicServiceRequestController extends Controller
{
    public function create(): View
    {
        return view('service-requests.create', [
            'requestTypes' => NstpServiceRequest::REQUEST_TYPES,
            'assistanceTypes' => NstpServiceRequest::ASSISTANCE_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $isAssistance = $request->input('request_type') === 'assistance';
        $validated = $request->validate([
            'request_type' => ['required', Rule::in(array_keys(NstpServiceRequest::REQUEST_TYPES))],
            'assistance_type' => [Rule::requiredIf($isAssistance), 'nullable', Rule::in(array_keys(NstpServiceRequest::ASSISTANCE_TYPES))],
            'requester_name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'email:rfc', 'max:180'],
            'contact_number' => ['required', 'string', 'max:40'],
            'student_number' => ['nullable', 'string', 'max:60'],
            'program' => ['nullable', 'string', 'max:180'],
            'graduation_year' => ['nullable', 'integer', 'between:1945,'.(now()->year + 1)],
            'purpose' => ['required', 'string', 'max:3000'],
            'event_name' => [Rule::requiredIf($isAssistance), 'nullable', 'string', 'max:180'],
            'event_date' => [Rule::requiredIf($isAssistance), 'nullable', 'date', 'after_or_equal:today'],
            'event_location' => [Rule::requiredIf($isAssistance), 'nullable', 'string', 'max:255'],
            'expected_participants' => ['nullable', 'integer', 'between:1,1000000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'privacy_consent' => ['accepted'],
        ]);

        if (! $isAssistance) {
            $validated['assistance_type'] = null;
            $validated['event_name'] = null;
            $validated['event_date'] = null;
            $validated['event_location'] = null;
            $validated['expected_participants'] = null;
        }

        $file = $request->file('attachment');
        $path = $file?->store('service-requests', 'local');
        abort_if($file && ! $path, 500, 'The supporting file could not be stored.');
        unset($validated['attachment'], $validated['privacy_consent']);

        try {
            $serviceRequest = NstpServiceRequest::create([
                ...$validated,
                'attachment_path' => $path,
                'attachment_original_name' => $file?->getClientOriginalName(),
                'attachment_mime_type' => $file?->getMimeType(),
                'attachment_size_bytes' => $file?->getSize(),
                'status' => 'submitted',
                'privacy_accepted_at' => now(),
            ]);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('service-requests.show', $serviceRequest)
            ->with('status', 'Your request was submitted successfully. Save your reference number.');
    }

    public function show(NstpServiceRequest $nstpServiceRequest): View
    {
        return view('service-requests.show', ['serviceRequest' => $nstpServiceRequest]);
    }
}
