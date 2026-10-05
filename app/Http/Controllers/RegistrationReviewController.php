<?php

namespace App\Http\Controllers;

use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use App\Models\NstpSection;
use App\Services\RegistrationDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationReviewController extends Controller
{
    public function __construct(private readonly RegistrationDocumentService $documents) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(StudentRegistration::STATUS_LABELS))],
        ]);

        $registrations = StudentRegistration::query()
            ->with('reviewer')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('reference_code', 'like', "%{$search}%")
                        ->orWhere('student_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'needs_correction' THEN 0 WHEN 'pending' THEN 1 WHEN 'under_review' THEN 2 ELSE 3 END")
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $checklists = $registrations->getCollection()
            ->mapWithKeys(fn (StudentRegistration $registration): array => [
                $registration->id => $this->documents->checklist($registration),
            ]);

        return view('registration-reviews.index', [
            ...$this->viewContext($request),
            'registrations' => $registrations,
            'checklists' => $checklists,
            'statuses' => StudentRegistration::STATUS_LABELS,
            'statusCounts' => StudentRegistration::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'registrationOpen' => SystemSetting::studentRegistrationIsOpen(),
            'registrationAcademicYear' => SystemSetting::studentRegistrationAcademicYear(),
            'registrationSemester' => SystemSetting::studentRegistrationSemester(),
            'semesters' => NstpSection::SEMESTERS,
        ]);
    }

    public function show(Request $request, StudentRegistration $registration): View
    {
        $registration->load('reviewer');

        return view('registration-reviews.show', [
            ...$this->viewContext($request),
            'registration' => $registration,
            'checklist' => $this->documents->checklist($registration),
            'documentStatuses' => StudentRegistration::DOCUMENT_STATUS_LABELS,
        ]);
    }

    public function update(Request $request, StudentRegistration $registration): RedirectResponse
    {
        $validated = $request->validate([
            'cor_review_status' => ['required', Rule::in(array_keys(StudentRegistration::DOCUMENT_STATUS_LABELS))],
            'formal_photo_review_status' => ['required', Rule::in(array_keys(StudentRegistration::DOCUMENT_STATUS_LABELS))],
            'review_notes' => [
                Rule::requiredIf(fn (): bool => in_array('needs_correction', [
                    $request->input('cor_review_status'),
                    $request->input('formal_photo_review_status'),
                ], true)),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $checklist = $this->documents->checklist($registration);
        $errors = [];
        foreach (['cor', 'formal_photo'] as $document) {
            if ($validated[$document.'_review_status'] === 'verified' && ! $checklist[$document]['complete']) {
                $errors[$document.'_review_status'] = 'This document cannot be verified because its stored file is missing, empty, too large, or has an unsupported type.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $documentDecisions = [$validated['cor_review_status'], $validated['formal_photo_review_status']];
        $status = in_array('needs_correction', $documentDecisions, true)
            ? 'needs_correction'
            : (collect($documentDecisions)->every(fn (string $decision): bool => $decision === 'verified')
                ? 'verified'
                : 'under_review');

        $registration->update([
            ...$validated,
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return redirect()
            ->route($this->routePrefix($request).'.registrations.show', $registration)
            ->with('status', $status === 'verified'
                ? 'Both required documents were marked as verified.'
                : ($status === 'needs_correction'
                    ? 'The registration was marked as needing correction.'
                    : 'The document review was saved.'));
    }

    public function preview(Request $request, StudentRegistration $registration, string $document): StreamedResponse
    {
        return $this->documentResponse($registration, $document, 'inline');
    }

    public function download(Request $request, StudentRegistration $registration, string $document): StreamedResponse
    {
        return $this->documentResponse($registration, $document, 'attachment');
    }

    private function documentResponse(StudentRegistration $registration, string $document, string $disposition): StreamedResponse
    {
        abort_unless(in_array($document, ['cor', 'formal_photo'], true), 404);
        $file = $this->documents->checklist($registration)[$document];
        abort_unless($file['exists'] && filled($file['path']), 404, 'The submitted document is no longer available.');

        return Storage::disk('local')->response(
            $file['path'],
            $file['name'] ?: $document.'.'.$file['extension'],
            ['Cache-Control' => 'private, no-store'],
            $disposition,
        );
    }

    /** @return array{layout: string, routePrefix: string} */
    private function viewContext(Request $request): array
    {
        $routePrefix = $this->routePrefix($request);

        return [
            'layout' => $routePrefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $routePrefix,
        ];
    }

    private function routePrefix(Request $request): string
    {
        return $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';
    }
}
