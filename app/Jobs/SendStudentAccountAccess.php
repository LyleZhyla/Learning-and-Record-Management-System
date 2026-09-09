<?php

namespace App\Jobs;

use App\Mail\StudentAccountAccessMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

class SendStudentAccountAccess implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $studentId) {}

    public function handle(): void
    {
        $student = User::query()
            ->where('role', 'student')
            ->where('status', 'active')
            ->find($this->studentId);

        if (! $student) {
            return;
        }

        $token = Password::broker()->createToken($student);
        $setupUrl = route('password.reset', [
            'token' => $token,
            'email' => $student->email,
        ]);

        Mail::to($student->email)->send(new StudentAccountAccessMail(
            recipientName: $student->name,
            accountEmail: $student->email,
            setupUrl: $setupUrl,
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Student account access email could not be delivered after retries.', [
            'student_id' => $this->studentId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
