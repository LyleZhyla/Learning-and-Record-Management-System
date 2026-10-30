<?php

namespace Tests\Feature;

use App\Models\BackupRestorationDrill;
use App\Models\DataPrivacyRequest;
use App\Models\Institution;
use App\Models\PrivacyConsent;
use App\Models\PrivacyIncident;
use App\Models\PrivacyNotice;
use App\Models\RetentionPolicy;
use App\Models\SensitiveFieldAccessLog;
use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\InstitutionProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivacyComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_privacy_request_records_the_exact_notice_version(): void
    {
        $notice = PrivacyNotice::where('is_active', true)->firstOrFail();

        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Version '.$notice->version)
            ->assertSee('Submit a privacy request');

        $this->post('/privacy/requests', [
            'requester_name' => 'Data Subject',
            'requester_email' => 'subject@example.test',
            'request_type' => 'correction',
            'details' => 'Please correct my contact number after identity verification.',
            'privacy_consent' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        $privacyRequest = DataPrivacyRequest::firstOrFail();
        $this->assertSame('submitted', $privacyRequest->status);
        $this->assertTrue($privacyRequest->due_at->isFuture());
        $this->assertDatabaseHas('privacy_consents', [
            'subject_email' => 'subject@example.test',
            'notice_version' => $notice->version,
            'purpose' => 'privacy_request',
            'source_id' => $privacyRequest->id,
        ]);
    }

    public function test_admin_publishes_a_new_notice_without_losing_historical_consent(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $oldNotice = PrivacyNotice::where('is_active', true)->firstOrFail();
        PrivacyConsent::create([
            'privacy_notice_id' => $oldNotice->id,
            'subject_email' => 'historic@example.test',
            'notice_version' => $oldNotice->version,
            'purpose' => 'student_registration',
            'accepted_at' => now(),
        ]);

        $this->actingAs($admin)->post('/admin/privacy-compliance/notices', [
            'version' => '2.0',
            'title' => 'Updated Privacy Notice',
            'content' => 'Updated institutional privacy terms and data subject rights.',
            'effective_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($oldNotice->fresh()->is_active);
        $this->assertDatabaseHas('privacy_notices', ['version' => '2.0', 'is_active' => true]);
        $this->assertDatabaseHas('privacy_consents', ['subject_email' => 'historic@example.test', 'notice_version' => $oldNotice->version]);
    }

    public function test_retention_policy_deletes_only_expired_records_in_the_active_institution(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $policy = RetentionPolicy::where('record_type', 'sensitive_field_access_logs')->firstOrFail();
        $policy->update(['retention_days' => 30]);
        $old = $this->sensitiveLog($admin, now()->subDays(31));
        $recent = $this->sensitiveLog($admin, now()->subDays(29));

        $this->actingAs($admin)->post('/admin/privacy-compliance/retention/'.$policy->id.'/enforce')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('sensitive_field_access_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('sensitive_field_access_logs', ['id' => $recent->id]);
        $this->assertSame(1, $policy->fresh()->last_affected_count);
    }

    public function test_retention_policies_are_isolated_per_institution(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $sourceInstitutionId = RetentionPolicy::firstOrFail()->institution_id;
        $other = Institution::create([
            'name' => 'Other State University',
            'short_name' => 'OSU',
            'slug' => 'other-state-university',
            'code' => 'OSU',
            'is_active' => true,
        ]);
        app(InstitutionProvisioner::class)->provision($other, $sourceInstitutionId);
        $otherPolicy = RetentionPolicy::withoutGlobalScope('institution')->where('institution_id', $other->id)->firstOrFail();

        $this->actingAs($admin)->put('/admin/privacy-compliance/retention/'.$otherPolicy->id, [
            'retention_days' => 90,
            'disposition' => $otherPolicy->disposition,
            'is_active' => 1,
        ])->assertNotFound();
    }

    public function test_expired_consent_is_anonymized_but_its_notice_version_is_retained(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $notice = PrivacyNotice::where('is_active', true)->firstOrFail();
        $consent = PrivacyConsent::create([
            'privacy_notice_id' => $notice->id,
            'subject_email' => 'expired@example.test',
            'notice_version' => $notice->version,
            'purpose' => 'student_registration',
            'ip_address' => '127.0.0.1',
            'accepted_at' => now()->subDays(31),
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ]);
        $policy = RetentionPolicy::where('record_type', 'privacy_consents')->firstOrFail();
        $policy->update(['retention_days' => 30]);

        $this->actingAs($admin)->post('/admin/privacy-compliance/retention/'.$policy->id.'/enforce')->assertRedirect();

        $consent->refresh();
        $this->assertSame("anonymized-{$consent->id}@privacy.invalid", $consent->subject_email);
        $this->assertSame($notice->version, $consent->notice_version);
        $this->assertNull($consent->ip_address);
    }

    public function test_scheduled_retention_command_enforces_active_institution_policies(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $policy = RetentionPolicy::where('record_type', 'sensitive_field_access_logs')->firstOrFail();
        $policy->update(['retention_days' => 30]);
        $old = $this->sensitiveLog($admin, now()->subDays(31));

        $this->artisan('privacy:enforce-retention')
            ->expectsOutputToContain('Retention enforcement complete.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('sensitive_field_access_logs', ['id' => $old->id]);
    }

    public function test_sensitive_student_directory_access_is_logged_without_field_values(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        User::factory()->create(['role' => 'student', 'status' => 'active', 'email' => 'private-student@example.test']);

        $this->actingAs($admin)->get('/admin/students')->assertOk();

        $log = SensitiveFieldAccessLog::where('route_name', 'admin.students.index')->firstOrFail();
        $this->assertContains('contact', $log->field_categories);
        $this->assertStringNotContainsString('private-student@example.test', $log->toJson());
    }

    public function test_anonymized_analytics_suppress_small_groups(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($admin)->get('/admin/privacy-compliance')
            ->assertOk()
            ->assertSee('Groups below 5 are suppressed')
            ->assertSee('&lt;5', false)
            ->assertDontSee('private-student@example.test');
    }

    public function test_incident_reporting_and_response_are_traceable(): void
    {
        $this->post('/privacy/incidents', [
            'title' => 'Misdirected student report',
            'description' => 'A report may have been sent to the wrong authorized office.',
            'severity' => 'high',
            'discovered_at' => now()->subMinute()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHas('status');

        $incident = PrivacyIncident::firstOrFail();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $this->actingAs($admin)->put('/admin/privacy-compliance/incidents/'.$incident->id, [
            'status' => 'contained',
            'containment_actions' => 'Recipient access was revoked and the file was removed.',
            'resolution_notes' => 'Impact assessment remains in progress.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('privacy_incidents', [
            'id' => $incident->id,
            'status' => 'contained',
            'handled_by' => $admin->id,
        ]);
    }

    public function test_system_admin_can_run_a_non_destructive_backup_restoration_drill(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $archive = app(DatabaseBackupService::class)->archive();
        $before = User::count();

        $this->actingAs($admin)->post('/admin/privacy-compliance/restoration-drills', [
            'archive_name' => $archive['name'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $drill = BackupRestorationDrill::firstOrFail();
        $this->assertSame('passed', $drill->status);
        $this->assertGreaterThan(0, $drill->tables_detected);
        $this->assertSame(64, strlen($drill->archive_checksum));
        $this->assertSame($before, User::count(), 'A restoration drill must not replace live records.');
    }

    public function test_export_request_download_contains_only_the_requesters_tenant_data(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'email' => 'export-me@example.test']);
        $privacyRequest = DataPrivacyRequest::create([
            'requester_user_id' => $student->id,
            'reference_number' => 'PRV-2026-EXPORT01',
            'requester_name' => $student->name,
            'requester_email' => $student->email,
            'request_type' => 'export',
            'details' => 'Send my data copy.',
            'due_at' => now()->addDays(15),
        ]);

        $this->actingAs($admin)->get('/admin/privacy-compliance/requests/'.$privacyRequest->id.'/export')
            ->assertOk()
            ->assertHeader('cache-control', 'no-store, private')
            ->assertJsonPath('account.email', 'export-me@example.test')
            ->assertJsonMissing(['password']);
    }

    private function sensitiveLog(User $actor, mixed $accessedAt): SensitiveFieldAccessLog
    {
        return SensitiveFieldAccessLog::create([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_role' => $actor->role,
            'resource_type' => 'StudentProfile',
            'field_categories' => ['identity'],
            'purpose' => 'Test access',
            'route_name' => 'test.sensitive',
            'accessed_at' => $accessedAt,
            'created_at' => $accessedAt,
            'updated_at' => $accessedAt,
        ]);
    }
}
