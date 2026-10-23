<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\CommunityProject;
use App\Models\CommunityProjectActivity;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilitatorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_graphical_analytics_for_assigned_sections_only(): void
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-01',
            'name' => 'Community Builders',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $otherSection = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $otherFacilitator->id,
            'code' => 'CWTS-02',
            'name' => 'Hidden Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);

        $students = collect(range(1, 3))->map(fn (int $number) => User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'email' => "facilitator-dashboard-{$number}@example.test",
        ]));
        foreach ($students as $student) {
            NstpEnrollment::create([
                'student_id' => $student->id,
                'component_id' => $component->id,
                'section_id' => $section->id,
                'academic_year' => '2026-2027',
                'semester' => 'first',
                'status' => 'enrolled',
            ]);
        }

        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'created_by' => $facilitator->id,
            'title' => 'Community Orientation',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'token' => str()->random(48),
            'qr_payload' => 'facilitator-dashboard',
            'qr_svg' => '',
            'status' => 'open',
        ]);
        foreach (['present', 'late', 'absent'] as $index => $status) {
            AttendanceRecord::create([
                'attendance_session_id' => $session->id,
                'student_id' => $students[$index]->id,
                'status' => $status,
                'checked_in_at' => $status === 'absent' ? null : now(),
                'source' => 'manual',
                'recorded_by' => $facilitator->id,
            ]);
        }

        $assessment = Assessment::create([
            'section_id' => $section->id,
            'created_by' => $facilitator->id,
            'title' => 'Community Reflection',
            'type' => 'activity',
            'max_score' => 100,
            'weight' => 20,
            'status' => 'published',
            'published_at' => now(),
        ]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $students[0]->id, 'submitted_at' => now(), 'score' => 90]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $students[1]->id, 'submitted_at' => now()]);

        $hiddenSession = AttendanceSession::create([
            'section_id' => $otherSection->id,
            'created_by' => $otherFacilitator->id,
            'title' => 'Hidden Attendance Activity',
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'token' => str()->random(48),
            'qr_payload' => 'hidden-dashboard',
            'qr_svg' => '',
            'status' => 'open',
        ]);
        AttendanceRecord::create(['attendance_session_id' => $hiddenSession->id, 'student_id' => $students[2]->id, 'status' => 'absent', 'source' => 'manual']);

        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertSee('Teaching analytics')
            ->assertSee('Status distribution')
            ->assertSee('Students per section')
            ->assertSee('Grading completion')
            ->assertSee('Community Builders')
            ->assertSee('Attendance rate: 66.7 percent', false)
            ->assertSee('1 graded')
            ->assertSee('1 still awaiting a score')
            ->assertSee('Community Orientation')
            ->assertDontSee('Hidden Section')
            ->assertDontSee('Hidden Attendance Activity');
    }

    public function test_activity_calendar_page_combines_scoped_facilitator_activities_and_supports_month_navigation(): void
    {
        $this->travelTo('2026-10-22 09:00:00');
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create([
            'component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01',
            'name' => 'Community Builders', 'academic_year' => '2026-2027', 'semester' => 'first',
            'capacity' => 40, 'status' => 'active',
        ]);
        $hiddenSection = NstpSection::create([
            'component_id' => $component->id, 'facilitator_id' => $otherFacilitator->id, 'code' => 'CWTS-02',
            'name' => 'Hidden Section', 'academic_year' => '2026-2027', 'semester' => 'first',
            'capacity' => 40, 'status' => 'active',
        ]);

        Assessment::create([
            'section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Community Reflection Deadline',
            'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'due_at' => '2026-11-12 17:00:00', 'status' => 'published',
        ]);
        Assessment::create([
            'section_id' => $hiddenSection->id, 'created_by' => $otherFacilitator->id, 'title' => 'Hidden Assessment Deadline',
            'type' => 'activity', 'max_score' => 100, 'weight' => 20, 'due_at' => '2026-11-12 17:00:00', 'status' => 'published',
        ]);
        $project = CommunityProject::create([
            'reference_number' => 'CP-2026-00020', 'title' => 'Barangay Learning Hub', 'component_id' => $component->id,
            'section_id' => $section->id, 'proposed_by' => $student->id, 'description' => 'A community learning activity.',
            'objectives' => 'Support learners', 'beneficiaries' => 'Barangay learners', 'location' => 'Barangay Learning Center',
            'approval_status' => 'approved',
            'implementation_status' => 'planning',
        ]);
        CommunityProjectActivity::create([
            'community_project_id' => $project->id, 'title' => 'Learning Kit Distribution',
            'scheduled_date' => '2026-11-18', 'status' => 'planned', 'created_by' => $facilitator->id,
        ]);
        AttendanceSession::create([
            'section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'November Field Briefing',
            'starts_at' => '2026-11-07 08:00:00', 'ends_at' => '2026-11-07 10:00:00',
            'token' => str()->random(48), 'qr_payload' => 'november-field-briefing', 'qr_svg' => '', 'status' => 'scheduled',
        ]);

        $this->actingAs($facilitator)->get('/facilitator/activity-calendar?month=2026-11')
            ->assertOk()
            ->assertSee('Activity calendar')
            ->assertSee('November 2026')
            ->assertSee('Community Reflection Deadline')
            ->assertSee('Learning Kit Distribution')
            ->assertSee('November Field Briefing')
            ->assertSee('3 scheduled items')
            ->assertSee('month=2026-10', false)
            ->assertSee('month=2026-12', false)
            ->assertDontSee('Hidden Assessment Deadline')
            ->assertDontSee('Hidden Section');

        $this->actingAs($facilitator)->get('/facilitator/dashboard')
            ->assertOk()
            ->assertDontSee('Monthly agenda')
            ->assertSee('href="'.route('facilitator.activity-calendar').'"', false);
    }
}
