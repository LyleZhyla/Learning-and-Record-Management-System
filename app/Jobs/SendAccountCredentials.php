<?php

namespace App\Jobs;

use App\Mail\AccountCreatedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAccountCredentials implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $userId,
        public readonly string $recipientName,
        public readonly string $accountEmail,
        public readonly string $temporaryPassword,
        public readonly string $roleLabel,
    ) {}

    public function handle(): void
    {
        Mail::to($this->accountEmail)->send(new AccountCreatedMail(
            recipientName: $this->recipientName,
            accountEmail: $this->accountEmail,
            temporaryPassword: $this->temporaryPassword,
            roleLabel: $this->roleLabel,
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Account credentials email could not be delivered after retries.', [
            'user_id' => $this->userId,
            'recipient' => $this->accountEmail,
            'exception' => $exception->getMessage(),
        ]);
    }
}
