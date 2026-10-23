<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
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
            ->assertSee('NSTP Components')
            ->assertSee('Recent NSTP accounts')
            ->assertDontSee('Module readiness')
            ->assertDontSee('Operations checklist');
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

    public function test_nstp_admin_dashboard_summarizes_current_term_student_and_attendance_data(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;
        $academicYear = $start.'-'.($start + 1);
        $semester = now()->month >= 6 ? 'first' : 'second';
        $section = NstpSection::create([
            'component_id' => $component->id,
            'code' => 'CWTS-DASH',
            'name' => 'Dashboard Section',
            'academic_year' => $academicYear,
            'semester' => $semester,
            'capacity' => 40,
            'status' => 'active',
        ]);

        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'status' => 'enrolled',
        ]);
        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'created_by' => $admin->id,
            'title' => 'Dashboard Attendance',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'token' => 'nstp-admin-dashboard-test',
            'qr_payload' => 'dashboard-test',
            'qr_svg' => '<svg></svg>',
            'status' => 'open',
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'present',
            'checked_in_at' => now(),
        ]);

        $this->actingAs($admin)->get('/nstp-admin/dashboard')
            ->assertOk()
            ->assertViewHas('enrolledStudentCount', 1)
            ->assertViewHas('sectionedStudentCount', 1)
            ->assertViewHas('attendanceRate', 100.0)
            ->assertSee('Student participation by component')
            ->assertSee('Attendance per component')
            ->assertSee('CWTS: 1 present, 0 late, 0 absent', false)
            ->assertSee('100.0% of enrolled students have a section');
    }

    public function test_nstp_admin_activity_calendar_shows_all_current_term_components_only(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $calendarMonth = now()->addMonth()->startOfMonth();
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;
        $academicYear = $start.'-'.($start + 1);
        $semester = now()->month >= 6 ? 'first' : 'second';

        foreach (['CWTS' => 'Community Calendar Activity', 'LTS' => 'Literacy Calendar Activity'] as $code => $title) {
            $component = NstpComponent::create(['code' => $code, 'name' => $code.' Program', 'is_active' => true]);
            $section = NstpSection::create([
                'component_id' => $component->id,
                'facilitator_id' => $facilitator->id,
                'code' => $code.'-CAL',
                'name' => $code.' Calendar Section',
                'academic_year' => $academicYear,
                'semester' => $semester,
                'capacity' => 40,
                'status' => 'active',
            ]);
            Assessment::create([
                'section_id' => $section->id,
                'created_by' => $facilitator->id,
                'title' => $title,
                'type' => 'activity',
                'max_score' => 100,
                'weight' => 20,
                'due_at' => $calendarMonth->copy()->addDays($code === 'CWTS' ? 4 : 8)->setTime(17, 0),
                'status' => 'published',
            ]);
        }

        $oldSection = NstpSection::create([
            'component_id' => NstpComponent::where('code', 'CWTS')->value('id'),
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-OLD',
            'name' => 'Previous Term Section',
            'academic_year' => '2024-2025',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        Assessment::create([
            'section_id' => $oldSection->id,
            'created_by' => $facilitator->id,
            'title' => 'Previous Term Hidden Activity',
            'type' => 'activity',
            'max_score' => 100,
            'weight' => 20,
            'due_at' => $calendarMonth->copy()->addDays(10)->setTime(17, 0),
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->get('/nstp-admin/activity-calendar?month='.$calendarMonth->format('Y-m'))
            ->assertOk()
            ->assertSee('Activity calendar')
            ->assertSee('Community Calendar Activity')
            ->assertSee('Literacy Calendar Activity')
            ->assertSee('across all active NSTP sections this term')
            ->assertDontSee('Previous Term Hidden Activity')
            ->assertDontSee('Previous Term Section');

        $this->actingAs($admin)->get('/nstp-admin/dashboard')
            ->assertOk()
            ->assertDontSee('Monthly agenda')
            ->assertSee('href="'.route('nstp_admin.activity-calendar').'"', false);
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
