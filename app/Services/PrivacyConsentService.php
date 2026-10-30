<?php

namespace App\Services;

use App\Models\PrivacyConsent;
use App\Models\PrivacyNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PrivacyConsentService
{
    public function activeNotice(): PrivacyNotice
    {
        return PrivacyNotice::query()->where('is_active', true)->latest('effective_at')->firstOrFail();
    }

    public function record(string $email, string $purpose, ?Model $source = null, ?User $user = null, ?string $ipAddress = null): PrivacyConsent
    {
        $notice = $this->activeNotice();

        return PrivacyConsent::create([
            'privacy_notice_id' => $notice->id,
            'user_id' => $user?->id,
            'subject_email' => $email,
            'notice_version' => $notice->version,
            'purpose' => $purpose,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->getKey(),
            'ip_address' => $ipAddress,
            'accepted_at' => now(),
        ]);
    }
}
