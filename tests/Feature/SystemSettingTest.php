<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $this->actingAs($student)->get('/admin/settings/account-protection')->assertForbidden();
        $this->actingAs($nstpAdmin)->get('/admin/settings/account-protection')->assertForbidden();
        $this->actingAs($nstpAdmin)->put('/admin/settings', ['settings_section' => 'security', 'inactivity_timeout_minutes' => 5])->assertForbidden();
        $this->actingAs($nstpAdmin)->post('/admin/settings/reset-defaults')->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Branding Configuration')
            ->assertSee('Branding configuration')
            ->assertDontSee('Student ID card design')
            ->assertSee('Account Protection')
            ->assertDontSee('Automatic logout timeout')
            ->assertDontSee('Student registration cycle')
            ->assertDontSee('Review Categories')
            ->assertDontSee('Workflow Rules')
            ->assertDontSee('Notification Rules')
            ->assertDontSee('Policies &amp; Requirements', false)
            ->assertSee('Landing Page Editor');
        $this->actingAs($superAdmin)->get('/admin/settings/account-protection')
            ->assertOk()
            ->assertSee('Account protection')
            ->assertSee('Automatic logout timeout')
            ->assertDontSee('Branding &amp; system colors', false);
        $this->actingAs($superAdmin)->get('/admin/settings')
            ->assertSee('Reset branding to default settings')
            ->assertSee('css/system-settings-reset.css', false);
        $this->actingAs($superAdmin)->put('/admin/settings', [
            'settings_section' => 'security',
            'inactivity_timeout_minutes' => 15,
        ])->assertSessionHasNoErrors();
        $this->actingAs($superAdmin)->put('/admin/settings', [
            'settings_section' => 'registration',
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

    public function test_super_admin_can_update_branding_colors_and_student_id_while_core_assets_stay_protected(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)->put('/admin/settings', [
            'settings_section' => 'branding',
            'organization_name' => 'Sample State University',
            'organization_short_name' => 'SSU',
            'program_name' => 'National Service Training Program',
            'program_short_name' => 'NSTP',
            'platform_name' => 'SSU NSTP Management Platform',
            'organization_website' => 'https://example.edu.ph',
            'system_primary_color' => '#4A174D',
            'system_secondary_color' => '#20102F',
            'system_accent_color' => '#B87333',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($superAdmin)->put('/admin/settings', [
            'settings_section' => 'student_id',
            'student_id_title' => 'UNIVERSITY ID',
            'student_id_primary_color' => '#4A174D',
            'student_id_secondary_color' => '#B87333',
            'student_id_accent_color' => '#F4C95D',
            'student_id_footer_text' => 'Property of Sample State University',
            'student_id_show_course' => '1',
            'student_id_show_component' => '1',
            'student_id_show_emergency_contact' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $branding = SystemSetting::branding();
        $this->assertSame('Sample State University', $branding['organization_name']);
        $this->assertSame('SSU', $branding['organization_short_name']);
        $this->assertNull($branding['organization_logo_path']);
        $this->assertNull($branding['program_logo_path']);
        $this->assertSame('#4A174D', SystemSetting::systemColors()['primary_color']);
        $this->assertSame('#20102F', SystemSetting::systemColors()['secondary_color']);
        $this->assertSame('#B87333', SystemSetting::systemColors()['accent_color']);
        $idDesign = SystemSetting::studentIdDesign();
        $this->assertSame('UNIVERSITY ID', $idDesign['title']);
        $this->assertSame('#4A174D', $idDesign['primary_color']);
        $this->assertSame('Property of Sample State University', $idDesign['footer_text']);
        $this->assertFalse($idDesign['show_emergency_contact']);

        $this->actingAs($superAdmin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('images/snapie-landscape-dark.png', false)
            ->assertSee('SSU NSTP');

        $this->actingAs($superAdmin)->get('/admin/settings')
            ->assertOk()
            ->assertSee('Protected brand assets')
            ->assertDontSee('Student ID card design')
            ->assertDontSee('name="organization_logo"', false)
            ->assertDontSee('name="program_logo"', false)
            ->assertSee('Sample State University')
            ->assertDontSee('UNIVERSITY ID');
    }

    public function test_each_settings_section_validates_only_its_own_fields(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)->put('/admin/settings', [
            'settings_section' => 'security',
            'inactivity_timeout_minutes' => 45,
        ])->assertSessionHasNoErrors();

        $this->assertSame(45, SystemSetting::inactivityTimeoutMinutes());

        $this->actingAs($superAdmin)->from('/admin/settings')->put('/admin/settings', [
            'settings_section' => 'branding',
            'organization_name' => 'Sample University',
            'organization_short_name' => 'SU',
            'program_name' => 'NSTP',
            'program_short_name' => 'NSTP',
            'platform_name' => 'Sample Platform',
            'organization_website' => 'https://example.edu.ph',
            'system_primary_color' => 'not-a-color',
            'system_secondary_color' => '#10264B',
            'system_accent_color' => '#15996F',
        ])->assertSessionHasErrors('system_primary_color');
    }

    public function test_super_admin_can_reset_branding_without_changing_module_settings_or_records(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        foreach ([
            'institution_branding' => json_encode(array_replace(SystemSetting::BRANDING_DEFAULTS, ['organization_name' => 'Changed University'])),
            'system_colors' => json_encode(['primary_color' => '#111111', 'secondary_color' => '#222222', 'accent_color' => '#333333']),
            'student_id_design' => json_encode(array_replace(SystemSetting::STUDENT_ID_DESIGN_DEFAULTS, ['title' => 'CHANGED ID'])),
            'student_registration_open' => '0',
            'student_registration_academic_year' => '2030-2031',
            'student_registration_semester' => 'second',
            'inactivity_timeout_minutes' => '5',
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $superAdmin->id]);
        }

        $this->actingAs($superAdmin)
            ->post(route('admin.settings.reset-defaults'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Branding and system colors were reset to their defaults. Accounts and module data were not changed.');

        $this->assertSame(SystemSetting::BRANDING_DEFAULTS, SystemSetting::branding());
        $this->assertSame(SystemSetting::SYSTEM_COLOR_DEFAULTS, SystemSetting::systemColors());
        $this->assertSame('CHANGED ID', SystemSetting::studentIdDesign()['title']);
        $this->assertFalse(SystemSetting::studentRegistrationIsOpen());
        $this->assertSame('2030-2031', SystemSetting::studentRegistrationAcademicYear());
        $this->assertSame('second', SystemSetting::studentRegistrationSemester());
        $this->assertSame(5, SystemSetting::inactivityTimeoutMinutes());
        $this->assertDatabaseHas('users', ['id' => $student->id]);
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
