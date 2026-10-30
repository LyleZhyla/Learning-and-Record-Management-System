<?php

namespace App\Services;

use App\Models\DataPrivacyRequest;
use App\Models\PrivacyConsent;
use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\User;

class PrivacyDataExportService
{
    public function build(DataPrivacyRequest $privacyRequest): array
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower($privacyRequest->requester_email)])->first();
        $registration = StudentRegistration::query()->whereRaw('LOWER(email) = ?', [strtolower($privacyRequest->requester_email)])->first();
        $profile = $user ? StudentProfile::query()->where('user_id', $user->id)->first() : null;

        return [
            'exported_at' => now()->toIso8601String(),
            'institution_id' => $privacyRequest->institution_id,
            'request_reference' => $privacyRequest->reference_number,
            'account' => $user?->only(['name', 'email', 'role', 'status', 'last_login_at', 'created_at']),
            'student_registration' => $registration?->makeHidden(['cor_path', 'formal_photo_path'])->toArray(),
            'student_profile' => $profile?->toArray(),
            'consents' => PrivacyConsent::query()
                ->whereRaw('LOWER(subject_email) = ?', [strtolower($privacyRequest->requester_email)])
                ->get(['notice_version', 'purpose', 'accepted_at', 'withdrawn_at'])
                ->toArray(),
        ];
    }
}
