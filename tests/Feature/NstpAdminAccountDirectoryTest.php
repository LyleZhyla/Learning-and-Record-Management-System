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
            ->assertSee('CWTS')->assertSee('Limited account details')
            ->assertDontSee('Reset password')->assertDontSee('Last sign in');

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts/'.$coordinator->id)
            ->assertOk()->assertSee('System-wide')->assertSee('CWTS');
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
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        return [$nstpAdmin, $coordinator, $facilitator, $student, $section];
    }
}
