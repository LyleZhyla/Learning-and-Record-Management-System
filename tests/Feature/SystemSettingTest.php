<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_update_the_inactivity_timeout(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/admin/settings')->assertForbidden();
        $this->actingAs($nstpAdmin)->get('/admin/settings')->assertForbidden();
        $this->actingAs($nstpAdmin)->put('/admin/settings', ['inactivity_timeout_minutes' => 5])->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/settings')->assertOk()->assertSee('Automatic logout timeout');
        $this->actingAs($superAdmin)->put('/admin/settings', [
            'inactivity_timeout_minutes' => 15,
            'student_registration_open' => '0',
            'student_registration_academic_year' => '2027-2028',
            'student_registration_semester' => 'second',
        ])->assertSessionHasNoErrors();

        $this->assertSame(15, SystemSetting::inactivityTimeoutMinutes());
        $this->assertFalse(SystemSetting::studentRegistrationIsOpen());
        $this->assertSame('2027-2028', SystemSetting::studentRegistrationAcademicYear());
        $this->assertSame('second', SystemSetting::studentRegistrationSemester());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'route_name' => 'admin.settings.update',
            'action' => 'update',
        ]);
    }

    public function test_user_is_logged_out_after_the_configured_inactivity_period(): void
    {
        SystemSetting::where('key', 'inactivity_timeout_minutes')->update(['value' => '5']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        Carbon::setTestNow('2026-08-29 10:00:00');

        $this->actingAs($student)->get('/student/dashboard')->assertOk();
        Carbon::setTestNow(now()->addMinutes(5));

        $this->get('/student/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHas('inactivity_timeout');

        $this->assertGuest();
        $log = AuditLog::where('user_id', $student->id)->where('action', 'inactivity_logout')->firstOrFail();
        $this->assertSame(401, $log->status_code);
        $this->assertSame(5, $log->metadata['timeout_minutes']);
    }

    public function test_super_admin_can_rebrand_the_platform_and_upload_institution_logos(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)->put('/admin/settings', [
            'inactivity_timeout_minutes' => 30,
            'student_registration_open' => '1',
            'student_registration_academic_year' => '2026-2027',
            'student_registration_semester' => 'first',
            'organization_name' => 'Sample State University',
            'organization_short_name' => 'SSU',
            'program_name' => 'National Service Training Program',
            'program_short_name' => 'NSTP',
            'platform_name' => 'SSU NSTP Management Platform',
            'organization_website' => 'https://example.edu.ph',
            'organization_logo' => UploadedFile::fake()->image('university.png'),
            'program_logo' => UploadedFile::fake()->image('program.png'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $branding = SystemSetting::branding();
        $this->assertSame('Sample State University', $branding['organization_name']);
        $this->assertSame('SSU', $branding['organization_short_name']);
        Storage::disk('public')->assertExists($branding['organization_logo_path']);
        Storage::disk('public')->assertExists($branding['program_logo_path']);

        $this->actingAs($superAdmin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('SSU NSTP')
            ->assertSee('Sample State University');
    }

    public function test_nstp_admin_can_manage_the_public_registration_period(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->patch('/nstp-admin/registrations/settings', [
            'student_registration_open' => '0',
            'student_registration_academic_year' => '2028-2029',
            'student_registration_semester' => 'second',
        ])->assertSessionHasNoErrors();

        $this->assertFalse(SystemSetting::studentRegistrationIsOpen());
        $this->assertSame('2028-2029', SystemSetting::studentRegistrationAcademicYear());
        $this->assertSame('second', SystemSetting::studentRegistrationSemester());
    }

    public function test_normal_activity_restarts_the_inactivity_timer(): void
    {
        SystemSetting::where('key', 'inactivity_timeout_minutes')->update(['value' => '5']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        Carbon::setTestNow('2026-08-29 10:00:00');

        $this->actingAs($student)->get('/student/dashboard')->assertOk();
        Carbon::setTestNow(now()->addMinutes(4));
        $this->get('/student/attendance')->assertOk();
        Carbon::setTestNow(now()->addMinutes(4));
        $this->get('/student/dashboard')->assertOk();

        $this->assertAuthenticatedAs($student);
        $this->assertSame(10080, config('session.lifetime'));
    }
}
