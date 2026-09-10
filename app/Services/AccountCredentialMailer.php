<?php

namespace App\Services;

use App\Jobs\SendAccountCredentials;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class AccountCredentialMailer
{
    public function send(User $user, string $temporaryPassword): bool
    {
        try {
            SendAccountCredentials::dispatch(
                userId: $user->id,
                recipientName: $user->name,
                accountEmail: $user->email,
                temporaryPassword: $temporaryPassword,
                roleLabel: $user->roleLabel(),
                requiresStudentDocuments: (bool) $user->must_upload_student_documents,
            );

            return true;
        } catch (Throwable $exception) {
            Log::error('Account credentials email could not be queued.', [
                'user_id' => $user->id,
                'recipient' => $user->email,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
