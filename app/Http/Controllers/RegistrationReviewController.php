<?php

namespace App\Http\Controllers;

use App\Models\NstpSection;
use App\Models\ReviewCategory;
use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\RegistrationDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationReviewController extends Controller
{
    public function __construct(
        private readonly RegistrationDocumentService $documents,
    ) {}

    public function index(Request $request): View
    {
        $statusLabels = ReviewCategory::labels('registration');
        $statusOrder = ReviewCategory::categories('registration')->pluck('slug')->values();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys($statusLabels))],
            'record_state' => ['nullable', Rule::in(['active', 'archived'])],
        ]);

        $recordState = $filters['record_state'] ?? 'active';

        $registrations = StudentRegistration::query()
            ->with(['reviewer', 'archiver'])
            ->when(
                $recordState === 'archived',
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->whereNull('archived_at'),
            )
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
            ->when($statusOrder->isNotEmpty(), function ($query) use ($statusOrder): void {
                $cases = $statusOrder->map(fn (string $status, int $index): string => "WHEN ? THEN {$index}")->implode(' ');
                $query->orderByRaw("CASE status {$cases} ELSE ? END", [...$statusOrder->all(), $statusOrder->count()]);
            })
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
            'statuses' => $statusLabels,
            'statusCounts' => StudentRegistration::query()
                ->whereNull('archived_at')
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'recordState' => $recordState,
            'activeCount' => StudentRegistration::whereNull('archived_at')->count(),
            'archivedCount' => StudentRegistration::whereNotNull('archived_at')->count(),
            'registrationOpen' => SystemSetting::studentRegistrationIsOpen(),
            'registrationAcademicYear' => SystemSetting::studentRegistrationAcademicYear(),
            'registrationSemester' => SystemSetting::studentRegistrationSemester(),
            'semesters' => NstpSection::SEMESTERS,
        ]);
    }

    public function show(Request $request, StudentRegistration $registration): View
    {
        $registration->load(['reviewer', 'archiver']);

        return view('registration-reviews.show', [
            ...$this->viewContext($request),
            'registration' => $registration,
            'checklist' => $this->documents->checklist($registration),
            'documentStatuses' => ReviewCategory::categories('registration_document', true),
        ]);
    }

    public function update(Request $request, StudentRegistration $registration): RedirectResponse
    {
        abort_if($registration->archived_at, 409, 'Restore this registration before changing its review decision.');

        $activeDocumentStatuses = ReviewCategory::categories('registration_document', true)->keyBy('slug');
        $selectedOutcomes = collect([
            $request->input('cor_review_status'),
            $request->input('formal_photo_review_status'),
        ])->map(fn (?string $status): string => $activeDocumentStatuses->get($status)?->outcome ?? 'pending');
        $validated = $request->validate([
            'cor_review_status' => ['required', Rule::in($activeDocumentStatuses->keys()->all())],
            'formal_photo_review_status' => ['required', Rule::in($activeDocumentStatuses->keys()->all())],
            'review_notes' => [
                Rule::requiredIf(fn (): bool => $selectedOutcomes->contains('correction')),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $checklist = $this->documents->checklist($registration);
        $errors = [];
        foreach (['cor', 'formal_photo'] as $document) {
            if (ReviewCategory::outcomeFor('registration_document', $validated[$document.'_review_status']) === 'approved' && ! $checklist[$document]['complete']) {
                $errors[$document.'_review_status'] = 'This document cannot be verified because its stored file is missing, empty, too large, or has an unsupported type.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $documentOutcomes = collect([$validated['cor_review_status'], $validated['formal_photo_review_status']])
            ->map(fn (string $decision): string => ReviewCategory::outcomeFor('registration_document', $decision));
        $statusOutcome = $documentOutcomes->contains('correction')
            ? 'correction'
            : ($documentOutcomes->every(fn (string $outcome): bool => $outcome === 'approved') ? 'approved' : 'in_progress');
        $status = ReviewCategory::defaultSlug('registration', $statusOutcome, match ($statusOutcome) {
            'approved' => 'verified',
            'correction' => 'needs_correction',
            default => 'under_review',
        });

        $account = DB::transaction(function () use ($registration, $validated, $status, $statusOutcome, $request): ?array {
            $registration->update([
                ...$validated,
                'status' => $status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return $statusOutcome === 'approved' ? $this->provisionStudentAccount($registration) : null;
        });

        $flash = [];
        if ($account && ! $account['existing']) {
            $flash = [
                'temporary_password' => $account['temporary_password'],
                'temporary_password_email' => $account['student']->email,
            ];
        }

        return redirect()
            ->route($this->routePrefix($request).'.registrations.show', $registration)
            ->with($flash + ['status' => $statusOutcome === 'approved'
                ? ($account['existing']
                    ? 'The registration remains approved and its student account is already in the Student Accounts list.'
                    : 'Enrollment approved. The student account was created and added to the Student Accounts list.')
                : ($statusOutcome === 'correction'
                    ? 'The registration was marked as needing correction.'
                    : 'The document review was saved.')]);
    }

    public function archive(Request $request, StudentRegistration $registration): RedirectResponse
    {
        if (! $registration->archived_at) {
            $registration->update(['archived_at' => now(), 'archived_by' => $request->user()->id]);
        }

        return redirect()->route($this->routePrefix($request).'.registrations.index')
            ->with('status', 'Registration '.$registration->reference_code.' was archived.');
    }

    public function restore(Request $request, StudentRegistration $registration): RedirectResponse
    {
        $registration->update(['archived_at' => null, 'archived_by' => null]);

        return redirect()->route($this->routePrefix($request).'.registrations.show', $registration)
            ->with('status', 'Registration '.$registration->reference_code.' was restored.');
    }

    public function destroy(Request $request, StudentRegistration $registration): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($registration->archived_at, 409, 'Archive this registration before permanently deleting it.');

        $request->validate([
            'confirmation' => ['required', Rule::in([$registration->reference_code])],
        ], [
            'confirmation.in' => 'Enter the registration reference code exactly as shown.',
        ]);

        $paths = collect([$registration->cor_path, $registration->formal_photo_path])->filter()->unique()->values();
        $referenceCode = $registration->reference_code;
        $registration->delete();

        if ($paths->isNotEmpty()) {
            Storage::disk('local')->delete($paths->all());
        }

        return redirect()->route('admin.registrations.index', ['record_state' => 'archived'])
            ->with('status', 'Registration '.$referenceCode.' was permanently deleted.');
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

    /** @return array{student: User, temporary_password: ?string, existing: bool} */
    private function provisionStudentAccount(StudentRegistration $registration): array
    {
        $existingProfile = StudentProfile::with('user')
            ->where('student_registration_id', $registration->id)
            ->first();

        if ($existingProfile) {
            return ['student' => $existingProfile->user, 'temporary_password' => null, 'existing' => true];
        }

        if (User::whereRaw('LOWER(email) = ?', [Str::lower($registration->email)])->exists()) {
            throw ValidationException::withMessages([
                'account' => 'A user account already uses this registration email. Resolve the duplicate before approving.',
            ]);
        }

        if (StudentProfile::where('student_number', $registration->student_number)->exists()) {
            throw ValidationException::withMessages([
                'account' => 'A student profile already uses this student number. Resolve the duplicate before approving.',
            ]);
        }

        $temporaryPassword = $this->generateTemporaryPassword();
        $student = User::create([
            'name' => collect([
                $registration->first_name,
                $registration->middle_name,
                $registration->last_name,
                $registration->extension_name,
            ])->filter(fn ($part) => filled($part))->implode(' '),
            'email' => Str::lower($registration->email),
            'password' => $temporaryPassword,
            'role' => 'student',
            'status' => 'active',
            'must_change_password' => true,
            'must_upload_student_documents' => false,
        ]);

        $student->studentProfile()->create([
            'student_registration_id' => $registration->id,
            'last_name' => $registration->last_name,
            'first_name' => $registration->first_name,
            'extension_name' => $registration->extension_name,
            'middle_name' => $registration->middle_name,
            'province' => $registration->province,
            'province_code' => $registration->province_code,
            'city_municipality' => $registration->city_municipality,
            'city_municipality_code' => $registration->city_municipality_code,
            'barangay' => $registration->barangay,
            'barangay_code' => $registration->barangay_code,
            'date_of_birth' => $registration->date_of_birth,
            'birth_province' => $registration->birth_province,
            'birth_province_code' => $registration->birth_province_code,
            'birth_city_municipality' => $registration->birth_city_municipality,
            'birth_city_municipality_code' => $registration->birth_city_municipality_code,
            'religion' => $registration->religion,
            'sex' => $registration->sex,
            'blood_type' => $registration->blood_type,
            'contact_number' => $registration->contact_number,
            'emergency_contact_name' => $registration->emergency_contact_name,
            'emergency_relationship' => $registration->emergency_relationship,
            'emergency_contact_number' => $registration->emergency_contact_number,
            'emergency_same_address' => $registration->emergency_same_address,
            'emergency_address' => $registration->emergency_address,
            'student_number' => $registration->student_number,
            'college' => $registration->college,
            'course' => $registration->course,
            'major' => $registration->major,
            'year_section' => $registration->year_section,
        ]);

        return ['student' => $student, 'temporary_password' => $temporaryPassword, 'existing' => false];
    }

    private function generateTemporaryPassword(): string
    {
        $groups = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%&*?'];
        $pool = implode('', $groups);
        $characters = array_map(fn (string $group) => $group[random_int(0, strlen($group) - 1)], $groups);

        while (count($characters) < 16) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        shuffle($characters);

        return implode('', $characters);
    }
}
