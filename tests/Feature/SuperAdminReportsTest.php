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

class SuperAdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'name' => 'Demo Student']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Week 1', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '<svg></svg>', 'status' => 'open']);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now(), 'source' => 'qr']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Activity 1', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Response', 'submitted_at' => now(), 'score' => 90, 'graded_by' => $facilitator->id, 'graded_at' => now()]);
    }

    public function test_super_admin_can_generate_all_report_types(): void
    {
        foreach (['students' => 'Demo Student', 'attendance' => 'Demo Student', 'grades' => 'Demo Student', 'sections' => 'CWTS-01'] as $type => $expected) {
            $this->actingAs($this->superAdmin)->get('/admin/reports?type='.$type.'&academic_year=2026-2027')
                ->assertOk()->assertSee($expected);
        }
    }

    public function test_super_admin_can_download_csv_and_open_print_view(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin/reports/attendance/export')
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($this->superAdmin)->get('/admin/reports/grades/print')
            ->assertOk()->assertSee('Print now')->assertSee('90.00%');
    }

    public function test_non_super_admin_cannot_access_reports(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $this->actingAs($admin)->get('/admin/reports')->assertForbidden();
    }
}
