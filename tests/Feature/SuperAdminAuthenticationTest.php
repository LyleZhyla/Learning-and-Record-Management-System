<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_public_student_registration_is_available(): void
    {
        $this->get('/register')->assertOk()->assertSee('Student Information Form');
        $this->get('/login')->assertOk()->assertSee('Complete your registration');
    }

    public function test_super_admin_can_sign_in(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertRedirect('/admin/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_student_signs_in_to_the_student_portal(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertRedirect('/student/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_super_admin_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Secure!Password2026'),
            'role' => 'super_admin',
            'status' => 'inactive',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Secure!Password2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_super_admin_dashboard_shows_enrollees_per_component_graph(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $lts = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'is_active' => true]);
        $students = User::factory()->count(3)->create(['role' => 'student', 'status' => 'active']);
        $rotcStudents = User::factory()->count(4)->create(['role' => 'student', 'status' => 'active']);

        NstpEnrollment::create(['student_id' => $students[0]->id, 'component_id' => $cwts->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $students[0]->id, 'component_id' => $cwts->id, 'academic_year' => '2026-2027', 'semester' => 'second', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $students[1]->id, 'component_id' => $cwts->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $students[2]->id, 'component_id' => $lts->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $rotcStudents[0]->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-1', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $rotcStudents[1]->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-31', 'status' => 'pending_approval']);
        NstpEnrollment::create(['student_id' => $rotcStudents[2]->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-31', 'status' => 'enrolled']);
        NstpEnrollment::create(['student_id' => $rotcStudents[3]->id, 'component_id' => $rotc->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'rotc_category' => 'MS-41', 'status' => 'pending_approval']);

        $this->actingAs($superAdmin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Students per component and ROTC category')
            ->assertSee('data-chart-orientation="vertical"', false)
            ->assertSee('aria-label="CWTS: 2 enrollees"', false)
            ->assertSee('aria-label="LTS: 1 enrollees"', false)
            ->assertSee('aria-label="MS-1: 1 enrollees"', false)
            ->assertSee('aria-label="MS-31: 2 enrollees"', false)
            ->assertSee('aria-label="MS-41: 1 enrollees"', false)
            ->assertDontSee('Current session')
            ->assertDontSee('Account overview')
            ->assertDontSee('Development roadmap')
            ->assertViewHas('componentEnrollments', fn ($components) => $components->firstWhere('code', 'CWTS')['count'] === 2
                && $components->firstWhere('code', 'LTS')['count'] === 1
                && $components->firstWhere('code', 'MS-1')['count'] === 1
                && $components->firstWhere('code', 'MS-31')['count'] === 2
                && $components->firstWhere('code', 'MS-41')['count'] === 1
            );
    }

    public function test_super_admin_dashboard_shows_active_students_without_a_current_component(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
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

        $this->actingAs($superAdmin)->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('unassignedStudentCount', 1)
            ->assertSee('Without component')
            ->assertSee('data-unassigned-student-count="1"', false);
    }

    public function test_super_admin_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('Old!Password2026'),
            'role' => 'super_admin',
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put('/admin/password', [
            'current_password' => 'Old!Password2026',
            'password' => 'New!Password2026',
            'password_confirmation' => 'New!Password2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('New!Password2026', $user->fresh()->password));
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
