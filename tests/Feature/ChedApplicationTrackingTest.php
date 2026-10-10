<?php

namespace Tests\Feature;

use App\Models\ChedApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChedApplicationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_track_a_manual_ched_application_without_system_submission(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/ched-applications', [
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'notes' => 'Prepare the initial CHED workbook.',
        ])->assertRedirect();

        $application = ChedApplication::firstOrFail();
        $this->assertSame('draft', $application->status);
        $this->assertSame('CHED-2026-00001', $application->reference_number);
        $this->assertNull($application->submitted_at);
        $this->assertDatabaseHas('ched_application_status_histories', [
            'ched_application_id' => $application->id,
            'from_status' => null,
            'to_status' => 'draft',
        ]);

        $this->actingAs($admin)->get('/admin/ched-applications/'.$application->id)
            ->assertOk()
            ->assertSee('The system only generates the workbook.')
            ->assertSee('CHED assigns those numbers.');
    }

    public function test_workbook_download_and_manual_email_status_are_recorded_in_history(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $application = ChedApplication::create([
            'reference_number' => 'CHED-2026-00001',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'draft',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get('/admin/ched-applications/'.$application->id.'/workbook')
            ->assertOk()->assertDownload();
        $application->refresh();
        $this->assertSame('workbook_prepared', $application->status);
        $this->assertNotNull($application->last_workbook_downloaded_at);

        $this->actingAs($admin)->put('/admin/ched-applications/'.$application->id.'/status', [
            'status' => 'emailed',
            'status_date' => '2026-10-10',
            'submission_email' => 'ched-regional@example.gov.ph',
            'ched_reference' => 'EMAIL-THREAD-001',
            'notes' => 'Workbook sent manually through institutional email.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame('emailed', $application->status);
        $this->assertSame('ched-regional@example.gov.ph', $application->submission_email);
        $this->assertDatabaseHas('ched_application_status_histories', [
            'ched_application_id' => $application->id,
            'from_status' => 'workbook_prepared',
            'to_status' => 'emailed',
        ]);
    }

    public function test_nstp_admin_can_access_tracker_but_facilitator_cannot(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/ched-applications')
            ->assertOk()->assertSee('CHED serial-number applications');
        $this->actingAs($facilitator)->get('/nstp-admin/ched-applications')->assertForbidden();
    }
}
