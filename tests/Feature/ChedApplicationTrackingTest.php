<?php

namespace Tests\Feature;

use App\Models\ChedApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChedApplicationTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_record_a_completed_manual_email_submission(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/ched-applications', [
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status_date' => '2026-10-10',
            'submission_email' => 'ched-regional@example.gov.ph',
            'ched_reference' => 'EMAIL-THREAD-001',
            'notes' => 'Official workbook was sent through institutional email.',
        ])->assertRedirect();

        $application = ChedApplication::firstOrFail();
        $this->assertSame('emailed', $application->status);
        $this->assertSame('CHED-2026-00001', $application->reference_number);
        $this->assertSame('2026-10-10', $application->submitted_at->toDateString());
        $this->assertSame('ched-regional@example.gov.ph', $application->submission_email);
        $this->assertDatabaseHas('ched_application_status_histories', [
            'ched_application_id' => $application->id,
            'from_status' => null,
            'to_status' => 'emailed',
        ]);

        $this->actingAs($admin)->get('/admin/ched-applications/'.$application->id)
            ->assertOk()
            ->assertSee('The source workbook remains in Reports Center.')
            ->assertSee('Open source report')
            ->assertDontSee('Download CHED workbook');
    }

    public function test_legacy_workbook_link_redirects_to_reports_and_manual_email_is_recorded(): void
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
            ->assertRedirect('/admin/reports?type=ched_semestral&academic_year=2026-2027&semester=first');
        $application->refresh();
        $this->assertSame('draft', $application->status);
        $this->assertNull($application->last_workbook_downloaded_at);

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
            'from_status' => 'draft',
            'to_status' => 'emailed',
        ]);
    }

    public function test_tracker_explains_the_reports_email_and_record_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/ched-applications')
            ->assertOk()
            ->assertSee('Track what happens after the CHED report is sent.')
            ->assertSee('Open CHED report')
            ->assertSee('Record an emailed submission')
            ->assertSee('No file upload')
            ->assertSee('Go to Reports Center')
            ->assertDontSee('Download CHED workbook');
    }

    public function test_nstp_admin_can_access_tracker_but_facilitator_cannot(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/ched-applications')
            ->assertOk()->assertSee('CHED Application Tracker');
        $this->actingAs($facilitator)->get('/nstp-admin/ched-applications')->assertForbidden();
    }
}
