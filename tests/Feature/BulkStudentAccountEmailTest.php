<?php

namespace Tests\Feature;

use App\Jobs\SendStudentAccountAccess;
use App\Mail\StudentAccountAccessMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkStudentAccountEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_nstp_admin_can_queue_account_emails_for_selected_students(): void
    {
        Queue::fake();
        $students = User::factory()->count(2)->create(['role' => 'student', 'status' => 'active']);

        foreach ([
            'super_admin' => '/admin/students/email-access',
            'nstp_admin' => '/nstp-admin/students/email-access',
        ] as $role => $url) {
            $admin = User::factory()->create(['role' => $role, 'status' => 'active']);
            $directoryUrl = str_replace('/email-access', '', $url);

            $this->actingAs($admin)->get($directoryUrl)
                ->assertOk()
                ->assertSee('Email selected students')
                ->assertSee('data-select-all-students', false);

            $this->actingAs($admin)->post($url, [
                'student_ids' => $students->pluck('id')->all(),
            ])->assertSessionHasNoErrors()
                ->assertSessionHas('status', '2 student account email(s) queued for delivery.');
        }

        Queue::assertPushed(SendStudentAccountAccess::class, 4);

        foreach ($students as $student) {
            Queue::assertPushed(fn (SendStudentAccountAccess $job): bool => $job->studentId === $student->id);
        }
    }

    public function test_bulk_account_email_requires_active_student_accounts(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $inactiveStudent = User::factory()->create(['role' => 'student', 'status' => 'inactive']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        foreach ([$inactiveStudent, $facilitator] as $invalidAccount) {
            $this->actingAs($admin)->post('/admin/students/email-access', [
                'student_ids' => [$invalidAccount->id],
            ])->assertSessionHasErrors('student_ids.0');
        }

        Queue::assertNothingPushed();
    }

    public function test_non_admin_accounts_cannot_use_bulk_student_email_routes(): void
    {
        Queue::fake();
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        foreach (['/admin/students/email-access', '/nstp-admin/students/email-access'] as $url) {
            $this->actingAs($facilitator)->post($url, ['student_ids' => [$student->id]])->assertForbidden();
        }

        Queue::assertNothingPushed();
    }

    public function test_bulk_email_job_sends_each_student_a_unique_password_setup_link(): void
    {
        Mail::fake();
        $student = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.test',
            'role' => 'student',
            'status' => 'active',
        ]);

        (new SendStudentAccountAccess($student->id))->handle();

        Mail::assertSent(StudentAccountAccessMail::class, function (StudentAccountAccessMail $mail) use ($student): bool {
            $html = $mail->render();

            return $mail->hasTo($student->email)
                && $mail->recipientName === $student->name
                && $mail->accountEmail === $student->email
                && str_contains($mail->setupUrl, '/reset-password/')
                && str_contains($html, 'Set password &amp; access account')
                && str_contains($html, 'Snapie waving hello')
                && str_contains($html, $student->email);
        });

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $student->email]);
    }
}
