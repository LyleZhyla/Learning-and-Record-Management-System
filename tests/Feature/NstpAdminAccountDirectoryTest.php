<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NstpAdminAccountDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_nstp_admin_sees_only_coordinator_facilitator_and_student_accounts(): void
    {
        [$nstpAdmin, $coordinator, $facilitator, $student] = $this->records();
        $superAdmin = User::factory()->create(['name' => 'Hidden Super Admin', 'role' => 'super_admin', 'status' => 'active']);
        $otherNstpAdmin = User::factory()->create(['name' => 'Hidden NSTP Admin', 'role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts')
            ->assertOk()
            ->assertSee($coordinator->name)
            ->assertSee($facilitator->name)
            ->assertSee($student->name)
            ->assertDontSee($superAdmin->name)
            ->assertDontSee($otherNstpAdmin->name)
            ->assertSee('Password access is restricted');

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts/'.$superAdmin->id)->assertNotFound();
    }

    public function test_staff_details_are_limited_to_name_email_and_component_coverage(): void
    {
        [$nstpAdmin, $coordinator, $facilitator] = $this->records();

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts/'.$facilitator->id)
            ->assertOk()->assertSee($facilitator->name)->assertSee($facilitator->email)
            ->assertSee('CWTS')->assertSee('Component assignment enabled')
            ->assertDontSee('Reset password')->assertDontSee('Last sign in');

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts/'.$coordinator->id)
            ->assertOk()->assertDontSee('System-wide')->assertSee('CWTS')
            ->assertSee('Assign NSTP component')->assertSee('Save component assignment');
    }

    public function test_nstp_admin_can_assign_components_to_coordinators_and_facilitators(): void
    {
        [$nstpAdmin, $coordinator, $facilitator] = $this->records();
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);

        foreach ([$coordinator, $facilitator] as $account) {
            $this->actingAs($nstpAdmin)
                ->patch('/nstp-admin/accounts/'.$account->id.'/component', ['nstp_component_id' => $rotc->id])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('users', [
                'id' => $account->id,
                'nstp_component_id' => $rotc->id,
            ]);
        }
    }

    public function test_nstp_admin_can_select_multiple_students_and_assign_a_component(): void
    {
        [$nstpAdmin, , , $student] = $this->records();
        $secondStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $academicYearStart = now()->month >= 6 ? now()->year : now()->year - 1;
        $academicYear = $academicYearStart.'-'.($academicYearStart + 1);
        $semester = now()->month >= 6 ? 'first' : 'second';

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts?role=student')
            ->assertOk()
            ->assertSee('Bulk student component assignment')
            ->assertSee('Assign selected students');

        $this->actingAs($nstpAdmin)->post('/nstp-admin/accounts/students/component', [
            'nstp_component_id' => $rotc->id,
            'student_ids' => [$student->id, $secondStudent->id],
        ])->assertRedirect()->assertSessionHasNoErrors();

        foreach ([$student, $secondStudent] as $assignedStudent) {
            $this->assertDatabaseHas('nstp_enrollments', [
                'student_id' => $assignedStudent->id,
                'component_id' => $rotc->id,
                'academic_year' => $academicYear,
                'semester' => $semester,
                'section_id' => null,
                'status' => 'enrolled',
            ]);
        }
    }

    public function test_nstp_admin_can_assign_an_ms_level_to_a_rotc_student(): void
    {
        [$nstpAdmin, , , $student] = $this->records();
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $enrollment = $student->nstpEnrollments()->firstOrFail();
        $enrollment->update([
            'component_id' => $rotc->id,
            'section_id' => null,
            'rotc_category' => 'MS-1',
            'status' => 'enrolled',
        ]);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts/'.$student->id)
            ->assertOk()->assertSee('MS level')->assertSee('MS-31');

        $this->actingAs($nstpAdmin)
            ->patch('/nstp-admin/accounts/'.$student->id.'/enrollments/'.$enrollment->id.'/rotc-category', [
                'rotc_category' => 'MS-31',
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'id' => $enrollment->id,
            'rotc_category' => 'MS-31',
            'rotc_approval_status' => 'approved',
            'rotc_approved_by' => $nstpAdmin->id,
            'status' => 'enrolled',
        ]);
    }

    public function test_bulk_student_component_assignment_rejects_non_students_and_non_nstp_admins(): void
    {
        [$nstpAdmin, $coordinator, , $student] = $this->records();
        $component = NstpComponent::firstOrFail();

        $this->actingAs($nstpAdmin)->post('/nstp-admin/accounts/students/component', [
            'nstp_component_id' => $component->id,
            'student_ids' => [$coordinator->id],
        ])->assertSessionHasErrors('student_ids.0');

        $this->actingAs($coordinator)->post('/nstp-admin/accounts/students/component', [
            'nstp_component_id' => $component->id,
            'student_ids' => [$student->id],
        ])->assertForbidden();
    }

    public function test_component_assignment_rejects_students_inactive_components_and_non_nstp_admins(): void
    {
        [$nstpAdmin, $coordinator, , $student] = $this->records();
        $inactive = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'default_section_capacity' => 40, 'is_active' => false]);

        $this->actingAs($nstpAdmin)
            ->patch('/nstp-admin/accounts/'.$student->id.'/component', ['nstp_component_id' => $inactive->id])
            ->assertNotFound();

        $this->actingAs($nstpAdmin)
            ->patch('/nstp-admin/accounts/'.$coordinator->id.'/component', ['nstp_component_id' => $inactive->id])
            ->assertSessionHasErrors('nstp_component_id');

        $this->actingAs($coordinator)
            ->patch('/nstp-admin/accounts/'.$coordinator->id.'/component', ['nstp_component_id' => null])
            ->assertForbidden();
    }

    public function test_all_current_student_records_are_visible_without_password_controls(): void
    {
        [$nstpAdmin, , $facilitator, $student, $section] = $this->records();
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Orientation', 'starts_at' => now()->subHour(), 'ends_at' => now(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '', 'status' => 'closed']);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now(), 'source' => 'qr']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Community Quiz', 'type' => 'quiz', 'max_score' => 20, 'weight' => 20, 'status' => 'published']);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'submitted_at' => now(), 'score' => 18, 'feedback' => 'Good work']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts/'.$student->id)
            ->assertOk()->assertSee($student->name)->assertSee($student->email)
            ->assertSee('Enrollment history')->assertSee('CWTS-01')
            ->assertSee('Orientation')->assertSee('Present')
            ->assertSee('Community Quiz')->assertSee('18.00 / 20.00')->assertSee('Good work')
            ->assertDontSee('Reset password');
    }

    public function test_nstp_admin_cannot_reset_an_account_password(): void
    {
        [$nstpAdmin, $coordinator] = $this->records();

        $this->actingAs($nstpAdmin)->put('/admin/users/'.$coordinator->id.'/password', [
            'password' => 'Changed!Password2026',
            'password_confirmation' => 'Changed!Password2026',
        ])->assertForbidden();
        $this->actingAs($nstpAdmin)->put('/nstp-admin/accounts/'.$coordinator->id.'/password')->assertNotFound();
    }

    private function records(): array
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $coordinator->update(['nstp_component_id' => $component->id]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        return [$nstpAdmin, $coordinator, $facilitator, $student, $section];
    }
}
