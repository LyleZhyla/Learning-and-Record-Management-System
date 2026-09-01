<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class NstpAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_nstp_admin_is_redirected_to_own_dashboard(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'nstp_admin',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertRedirect('/nstp-admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_nstp_admin_can_view_own_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($user)
            ->get('/nstp-admin/dashboard')
            ->assertOk()
            ->assertSee('NSTP Admin Dashboard')
            ->assertSee('Open sectioning')
            ->assertDontSee('NSTP Components');
    }

    public function test_nstp_admin_dashboard_shows_active_students_without_a_current_component(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $assignedStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        User::factory()->create(['role' => 'student', 'status' => 'active']);
        User::factory()->create(['role' => 'student', 'status' => 'inactive']);
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        NstpEnrollment::create([
            'student_id' => $assignedStudent->id,
            'component_id' => $component->id,
            'academic_year' => $start.'-'.($start + 1),
            'semester' => now()->month >= 6 ? 'first' : 'second',
            'status' => 'enrolled',
        ]);

        $this->actingAs($admin)->get('/nstp-admin/dashboard')
            ->assertOk()
            ->assertViewHas('unassignedStudentCount', 1)
            ->assertSee('Without component')
            ->assertSee('data-unassigned-student-count="1"', false);
    }

    public function test_nstp_admin_cannot_access_super_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
    }

    public function test_super_admin_cannot_access_nstp_admin_routes(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($user)->get('/nstp-admin/dashboard')->assertForbidden();
    }
}
