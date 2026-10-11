<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
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
        $this->get('/login')
            ->assertOk()
            ->assertSee('Complete your registration')
            ->assertSee('data-password-visibility-toggle', false)
            ->assertSee('aria-label="Show password"', false)
            ->assertSee('login-password-toggle.js');
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

    public function test_imported_student_can_sign_in_when_copied_credentials_have_outer_whitespace(): void
    {
        $student = User::factory()->create([
            'email' => 'imported.student@example.test',
            'password' => Hash::make('Generated!Pass2026'),
            'role' => 'student',
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $this->post('/login', [
            'email' => '  IMPORTED.STUDENT@EXAMPLE.TEST  ',
            'password' => "  Generated!Pass2026\r\n",
        ])->assertRedirect('/student/dashboard');

        $this->assertAuthenticatedAs($student);
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

    public function test_super_admin_dashboard_shows_account_access_overview_by_role(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        User::factory()->count(2)->create(['role' => 'student', 'status' => 'active']);
        User::factory()->create(['role' => 'student', 'status' => 'inactive']);
        User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        User::factory()->count(2)->create(['role' => 'coordinator', 'status' => 'inactive']);

        $this->actingAs($superAdmin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Account access overview')
            ->assertSee('Daily attendance rate')
            ->assertSee('aria-label="Student: 2 active, 1 inactive"', false)
            ->assertSee('aria-label="Facilitator: 1 active, 0 inactive"', false)
            ->assertSee('aria-label="Coordinator: 0 active, 2 inactive"', false)
            ->assertDontSee('Enrollees per component and ROTC category')
            ->assertViewHas('accountAccessOverview', fn ($accounts) => $accounts->firstWhere('role', 'student')['active'] === 2
                && $accounts->firstWhere('role', 'student')['inactive'] === 1
                && $accounts->firstWhere('role', 'facilitator')['active'] === 1
                && $accounts->firstWhere('role', 'coordinator')['inactive'] === 2
            );
    }

    public function test_super_admin_dashboard_shows_current_term_attendance_line_graph(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;
        $academicYear = $start.'-'.($start + 1);
        $semester = now()->month >= 6 ? 'first' : 'second';
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-DASH',
            'name' => 'Dashboard Section',
            'academic_year' => $academicYear,
            'semester' => $semester,
            'capacity' => 40,
            'status' => 'active',
        ]);
        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'created_by' => $facilitator->id,
            'title' => 'Dashboard Attendance',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(3),
            'token' => str()->random(48),
            'qr_payload' => 'dashboard-test',
            'qr_svg' => '<svg></svg>',
            'status' => 'closed',
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'checked_in_at' => now()->subDay(),
            'source' => 'qr',
        ]);

        $this->actingAs($superAdmin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Platform activity and access overview')
            ->assertSee('Attendance rate line graph')
            ->assertSee(now()->subDay()->format('M j'))
            ->assertSee('100.0%')
            ->assertViewHas('attendanceChart', fn (array $chart): bool => $chart['points']->count() === 1
                && $chart['points']->first()['rate'] === 100.0);
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
