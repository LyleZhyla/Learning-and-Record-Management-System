<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\SectionSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_dashboard_summarizes_progress_and_next_actions(): void
    {
        $facilitator = User::factory()->create(['name' => 'Maria Facilitator', 'role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['name' => 'Juan Student', 'role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-01',
            'name' => 'Section 1',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        SectionSchedule::create(['section_id' => $section->id, 'day_of_week' => 6, 'starts_at' => '08:00:00', 'ends_at' => '12:00:00']);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        foreach (['present', 'late', 'absent'] as $index => $status) {
            $session = AttendanceSession::create([
                'section_id' => $section->id,
                'created_by' => $facilitator->id,
                'title' => 'Session '.($index + 1),
                'starts_at' => now()->subDays(3 - $index),
                'ends_at' => now()->subDays(3 - $index)->addHour(),
                'token' => str()->random(48),
                'qr_payload' => '',
                'qr_svg' => '',
                'status' => 'closed',
            ]);
            AttendanceRecord::create([
                'attendance_session_id' => $session->id,
                'student_id' => $student->id,
                'status' => $status,
                'checked_in_at' => $session->starts_at,
                'recorded_by' => $facilitator->id,
            ]);
        }

        $completed = Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Completed Reflection', 'type' => 'activity', 'max_score' => 20, 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $completed->id, 'student_id' => $student->id, 'answer_text' => 'Done', 'submitted_at' => now()]);
        Assessment::create(['section_id' => $section->id, 'created_by' => $facilitator->id, 'title' => 'Community Action Plan', 'type' => 'project', 'max_score' => 50, 'due_at' => now()->addDays(2), 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertViewHas('attendanceRate', 66.7)
            ->assertViewHas('assessmentRate', 50.0)
            ->assertSee('Session participation')
            ->assertSee('Community Action Plan')
            ->assertSee('Maria Facilitator')
            ->assertSee('Saturday')
            ->assertSee('2/3', false)
            ->assertSee('student-dashboard.css', false);
    }

    public function test_student_without_enrollment_gets_a_clear_setup_action(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Complete your NSTP setup')
            ->assertSee('Choose NSTP component')
            ->assertSee(route('student.component.edit'), false)
            ->assertDontSee('Session participation');
    }
}
