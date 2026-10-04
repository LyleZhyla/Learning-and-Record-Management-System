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
use App\Services\GradeService;
use App\Services\ProgressMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_summary_combines_grades_missing_work_and_attendance(): void
    {
        [$coordinator, $facilitator, $student, $section] = $this->records();
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->where('name', 'Quizzes')->firstOrFail();
        $graded = Assessment::create([
            'section_id' => $section->id,
            'grading_category_id' => $category->id,
            'created_by' => $facilitator->id,
            'title' => 'Graded Quiz',
            'type' => 'quiz',
            'max_score' => 100,
            'weight' => 20,
            'due_at' => now()->subDays(2),
            'status' => 'published',
            'published_at' => now()->subWeek(),
        ]);
        AssessmentSubmission::create([
            'assessment_id' => $graded->id,
            'student_id' => $student->id,
            'submitted_at' => now()->subDays(3),
            'score' => 90,
            'graded_by' => $facilitator->id,
            'graded_at' => now(),
        ]);
        Assessment::create([
            'section_id' => $section->id,
            'grading_category_id' => $category->id,
            'created_by' => $facilitator->id,
            'title' => 'Missing Quiz',
            'type' => 'quiz',
            'max_score' => 100,
            'weight' => 20,
            'due_at' => now()->subDay(),
            'status' => 'published',
            'published_at' => now()->subWeek(),
        ]);
        $session = AttendanceSession::create([
            'section_id' => $section->id,
            'created_by' => $facilitator->id,
            'title' => 'Completed Session',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'token' => str()->random(48),
            'qr_payload' => '',
            'qr_svg' => '',
            'status' => 'closed',
        ]);
        AttendanceRecord::create([
            'attendance_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => 'absent',
            'source' => 'system',
            'recorded_by' => $facilitator->id,
        ]);

        $summary = app(ProgressMonitoringService::class)->summary($student, $section->id);

        $this->assertSame(50.0, $summary['completion_percentage']);
        $this->assertSame(90.0, $summary['current_percentage']);
        $this->assertSame(1, $summary['missing_count']);
        $this->assertSame(0.0, $summary['attendance_rate']);
        $this->assertSame('at_risk', $summary['overall_status']);
        $this->assertCount(2, $summary['attention_items']);

        $this->actingAs($student)->get('/student/grades')
            ->assertOk()
            ->assertSee('Missing Quiz')
            ->assertSee('Needs attention')
            ->assertSee('Missing requirements');

        $this->actingAs($coordinator)->get('/coordinator/performance?section_id='.$section->id.'&progress_status=at_risk')
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('Average attendance')
            ->assertSee('Needs attention');
    }

    public function test_gradebook_ajax_returns_updated_progress_fields(): void
    {
        [, $facilitator, $student, $section] = $this->records();
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->where('name', 'Class Standing')->firstOrFail();
        $assessment = Assessment::create([
            'section_id' => $section->id,
            'grading_category_id' => $category->id,
            'created_by' => $facilitator->id,
            'title' => 'Activity 1',
            'type' => 'activity',
            'max_score' => 50,
            'weight' => 20,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($facilitator)->putJson('/facilitator/grades/'.$section->id.'/scores', [
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'score' => 45,
        ])->assertOk()
            ->assertJsonPath('current_percentage', 90)
            ->assertJsonPath('completion_percentage', 100)
            ->assertJsonPath('pending_count', 0)
            ->assertJsonStructure(['progress_status', 'progress_label']);
    }

    private function records(): array
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-PROGRESS',
            'name' => 'Progress Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        return [$coordinator, $facilitator, $student, $section];
    }
}
