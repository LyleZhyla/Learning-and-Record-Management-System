<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_page_views_and_updates_are_recorded_without_form_contents(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/student/dashboard')->assertOk();
        $this->actingAs($student)->put('/student/profile', [
            'name' => 'Updated Student',
            'email' => $student->email,
            'private_note' => 'must-not-be-logged',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $student->id,
            'route_name' => 'student.dashboard',
            'action' => 'view',
            'status_code' => 200,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $student->id,
            'route_name' => 'student.profile.update',
            'action' => 'update',
        ]);
        $this->assertStringNotContainsString('must-not-be-logged', AuditLog::where('route_name', 'student.profile.update')->firstOrFail()->toJson());
    }

    public function test_login_and_logout_are_attributed_to_the_user(): void
    {
        $user = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/student/dashboard');
        $this->post('/logout')->assertRedirect('/login');

        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'route_name' => 'login.store', 'action' => 'login']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'route_name' => 'logout', 'action' => 'logout']);
    }

    public function test_only_super_admin_can_view_system_logs(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/admin/system-logs')->assertForbidden();
        $this->actingAs($superAdmin)->get('/admin/system-logs')->assertOk()->assertSee('User activity logs');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $student->id,
            'route_name' => 'admin.system-logs.index',
            'status_code' => 403,
        ]);
    }
}
