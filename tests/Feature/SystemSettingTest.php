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
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/admin/settings')->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/settings')->assertOk()->assertSee('Automatic logout timeout');
        $this->actingAs($superAdmin)->put('/admin/settings', [
            'inactivity_timeout_minutes' => 15,
        ])->assertSessionHasNoErrors();

        $this->assertSame(15, SystemSetting::inactivityTimeoutMinutes());
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
