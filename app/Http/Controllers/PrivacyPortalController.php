<?php

namespace App\Http\Controllers;

use App\Models\DataPrivacyRequest;
use App\Models\PrivacyIncident;
use App\Services\InstitutionContext;
use App\Services\PrivacyConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrivacyPortalController extends Controller
{
    public function __construct(private PrivacyConsentService $consents) {}

    public function show(Request $request): View
    {
        return view('privacy.portal', [
            'notice' => $this->consents->activeNotice(),
            'user' => $request->user(),
        ]);
    }

    public function storeRequest(Request $request, InstitutionContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:150'],
            'requester_email' => ['required', 'email', 'max:255'],
            'request_type' => ['required', Rule::in(array_keys(DataPrivacyRequest::TYPES))],
            'details' => ['required', 'string', 'max:5000'],
            'privacy_consent' => ['accepted'],
        ]);
        $privacyRequest = DataPrivacyRequest::create([
            ...collect($validated)->except('privacy_consent')->all(),
            'institution_id' => $context->id(),
            'requester_user_id' => $request->user()?->id,
            'reference_number' => 'PRV-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
            'due_at' => now()->addDays(15),
        ]);
        $this->consents->record($privacyRequest->requester_email, 'privacy_request', $privacyRequest, $request->user(), $request->ip());

        return back()->with('status', 'Privacy request submitted. Reference: '.$privacyRequest->reference_number);
    }

    public function storeIncident(Request $request, InstitutionContext $context): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'severity' => ['required', Rule::in(PrivacyIncident::SEVERITIES)],
            'discovered_at' => ['required', 'date', 'before_or_equal:now'],
        ]);
        $incident = PrivacyIncident::create([
            ...$validated,
            'institution_id' => $context->id(),
            'reported_by' => $request->user()?->id,
            'reference_number' => 'INC-'.now()->format('Y').'-'.Str::upper(Str::random(8)),
        ]);

        return back()->with('status', 'Privacy incident reported. Reference: '.$incident->reference_number);
    }
}
