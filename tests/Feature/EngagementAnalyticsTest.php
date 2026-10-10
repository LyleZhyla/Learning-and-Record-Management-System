<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\CommunityProject;
use App\Models\LearningMaterial;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngagementAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_engagement_score_combines_existing_student_signals(): void
    {
        [$student, $facilitator, $component, $section] = $this->enrolledStudent('Engaged Student');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $session = AttendanceSession::create([
            'section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Completed Session',
            'starts_at' => now()->subHours(3), 'ends_at' => now()->subHours(2), 'token' => str()->random(48),
            'qr_payload' => 'engagement-test', 'qr_svg' => '<svg></svg>', 'status' => 'closed',
        ]);
        AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'present', 'checked_in_at' => now()->subHours(3), 'source' => 'qr']);
        $material = LearningMaterial::create(['component_id' => $component->id, 'section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Module 1', 'external_url' => 'https://example.test/module', 'status' => 'published', 'published_at' => now()->subDay()]);
        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Activity 1', 'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()->subDay()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Done', 'submitted_at' => now()]);
        $project = CommunityProject::create([
            'reference_number' => 'CP-ENGAGEMENT-001', 'title' => 'Engagement Project', 'component_id' => $component->id,
            'section_id' => $section->id, 'proposed_by' => $student->id, 'description' => 'Project used for analytics testing.',
            'objectives' => 'Verify task participation.', 'beneficiaries' => 'Partner community', 'location' => 'Test location',
            'approval_status' => 'approved', 'implementation_status' => 'ongoing',
        ]);
        ProjectTask::create([
            'community_project_id' => $project->id, 'title' => 'Completed field activity', 'assigned_to' => $student->id,
            'assigned_by' => $facilitator->id, 'priority' => 'normal', 'status' => 'completed',
            'progress_percentage' => 100, 'completed_at' => now(),
        ]);

        foreach (range(1, 8) as $index) {
            $this->audit($student, 'login', 'login.store', now()->subDays($index));
        }
        $this->audit($student, 'download', 'student.materials.download', now(), ['route_parameters' => ['material' => ['type' => 'LearningMaterial', 'id' => $material->id]]]);

        $this->actingAs($admin)->get('/admin/engagement-analytics')
            ->assertOk()
            ->assertSee('Engaged Student')
            ->assertSee('100.0%')
            ->assertSee('5/5 signals')
            ->assertSee('Engaged');
    }

    public function test_coordinator_and_facilitator_only_see_students_in_their_scope(): void
    {
        [$visibleStudent, $facilitator, $component] = $this->enrolledStudent('Visible Student');
        [$hiddenStudent] = $this->enrolledStudent('Hidden Student', 'LTS');
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);

        $this->actingAs($coordinator)->get('/coordinator/engagement-analytics')
            ->assertOk()->assertSee($visibleStudent->name)->assertDontSee($hiddenStudent->name);
        $this->actingAs($facilitator)->get('/facilitator/engagement-analytics')
            ->assertOk()->assertSee($visibleStudent->name)->assertDontSee($hiddenStudent->name);
    }

    public function test_student_only_sees_own_engagement_summary(): void
    {
        [$student] = $this->enrolledStudent('My Engagement Student');
        [$otherStudent] = $this->enrolledStudent('Another Student', 'ROTC');

        $this->actingAs($student)->get('/student/engagement-analytics')
            ->assertOk()
            ->assertSee('My engagement summary')
            ->assertSee($student->name)
            ->assertDontSee($otherStudent->name)
            ->assertDontSee('Apply filters');
    }

    public function test_nstp_admin_can_open_institution_wide_engagement_analytics(): void
    {
        [$student] = $this->enrolledStudent('Institution Student');
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/engagement-analytics')
            ->assertOk()
            ->assertSee('Student engagement overview')
            ->assertSee($student->name);
    }

    private function enrolledStudent(string $name, string $componentCode = 'CWTS'): array
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['name' => $name, 'role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => $componentCode, 'name' => $componentCode.' Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => $componentCode.'-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        return [$student, $facilitator, $component, $section];
    }

    private function audit(User $student, string $action, string $routeName, mixed $createdAt, ?array $metadata = null): void
    {
        AuditLog::create([
            'user_id' => $student->id, 'actor_name' => $student->name, 'actor_email' => $student->email,
            'actor_role' => 'student', 'action' => $action, 'description' => 'Test engagement event',
            'method' => 'GET', 'route_name' => $routeName, 'path' => '/student/test', 'status_code' => 200,
            'duration_ms' => 1, 'metadata' => $metadata, 'created_at' => $createdAt,
        ]);
    }
}
