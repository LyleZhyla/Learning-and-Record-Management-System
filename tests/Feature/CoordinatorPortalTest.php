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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinatorPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'password' => Hash::make('Secure!Password2026')]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $session = AttendanceSession::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Coordinator Demo Attendance', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'token' => str()->random(48), 'qr_payload' => 'test', 'qr_svg' => '<svg></svg>', 'status' => 'open']);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now(), 'source' => 'qr']);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Activity 1', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Response', 'submitted_at' => now(), 'score' => 90, 'graded_by' => $facilitator->id, 'graded_at' => now()]);
    }

    public function test_coordinator_signs_in_to_own_dashboard(): void
    {
        $this->post('/login', ['email' => $this->coordinator->email, 'password' => 'Secure!Password2026'])
            ->assertRedirect('/coordinator/dashboard');
        $this->assertAuthenticatedAs($this->coordinator);
    }

    public function test_coordinator_can_open_all_read_only_monitoring_pages(): void
    {
        foreach (['/coordinator/dashboard', '/coordinator/components', '/coordinator/sections', '/coordinator/attendance', '/coordinator/performance', '/coordinator/profile'] as $path) {
            $this->actingAs($this->coordinator)->get($path)->assertOk();
        }
    }

    public function test_coordinator_cannot_access_management_routes(): void
    {
        $this->actingAs($this->coordinator)->get('/admin/users')->assertForbidden();
        $this->actingAs($this->coordinator)->get('/nstp-admin/sections')->assertForbidden();
        $this->actingAs($this->coordinator)->get('/facilitator/attendance')->assertForbidden();
    }
}
