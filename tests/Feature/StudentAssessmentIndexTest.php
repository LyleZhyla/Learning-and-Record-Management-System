<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAssessmentIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $facilitator;

    private NstpSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $this->section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $this->facilitator->id,
            'code' => 'CWTS-ASSESS',
            'name' => 'Assessment Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create([
            'student_id' => $this->student->id,
            'component_id' => $component->id,
            'section_id' => $this->section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
    }

    public function test_student_assessment_page_shows_category_filters_and_status_overview(): void
    {
        $activity = $this->assessment('Community Reflection', 'activity');
        $this->assessment('Barangay Action Project', 'project');
        $quiz = $this->assessment('Safety Knowledge Quiz', 'quiz');
        $this->assessment('Final Written Exam', 'exam');
        $this->assessment('Hidden Draft', 'project', 'draft');

        AssessmentSubmission::create([
            'assessment_id' => $activity->id,
            'student_id' => $this->student->id,
            'answer_text' => 'Submitted response',
            'submitted_at' => now(),
        ]);
        AssessmentSubmission::create([
            'assessment_id' => $quiz->id,
            'student_id' => $this->student->id,
            'answer_text' => 'Graded response',
            'submitted_at' => now(),
            'score' => 18,
        ]);

        $this->actingAs($this->student)
            ->get(route('student.assessments.index'))
            ->assertOk()
            ->assertSee('Filter by category')
            ->assertSee('Activities')
            ->assertSee('Projects')
            ->assertSee('Quizzes')
            ->assertSee('Exams')
            ->assertSee('Community Reflection')
            ->assertSee('Barangay Action Project')
            ->assertSee('Safety Knowledge Quiz')
            ->assertSee('Final Written Exam')
            ->assertSee('Turned in')
            ->assertSee('Graded')
            ->assertDontSee('Hidden Draft');

    }

    public function test_student_can_filter_assessments_by_category(): void
    {
        $this->assessment('Reflection Activity', 'activity');
        $this->assessment('Clean-up Project', 'project');
        $this->assessment('Second Project', 'project');
        $this->assessment('Module Quiz', 'quiz');

        $this->actingAs($this->student)
            ->get(route('student.assessments.index', ['type' => 'project']))
            ->assertOk()
            ->assertSee('2 assessments found')
            ->assertSee('Clean-up Project')
            ->assertSee('Second Project')
            ->assertDontSee('Reflection Activity')
            ->assertDontSee('Module Quiz')
            ->assertSee('Clear filter')
            ->assertSee('aria-current="page"', false);
    }

    public function test_student_assessment_category_filter_rejects_unknown_values(): void
    {
        $this->actingAs($this->student)
            ->from(route('student.assessments.index'))
            ->get(route('student.assessments.index', ['type' => 'essay']))
            ->assertRedirect(route('student.assessments.index'))
            ->assertSessionHasErrors('type');
    }

    private function assessment(string $title, string $type, string $status = 'published'): Assessment
    {
        return Assessment::create([
            'section_id' => $this->section->id,
            'created_by' => $this->facilitator->id,
            'title' => $title,
            'type' => $type,
            'instructions' => 'Complete the posted instructions and submit your work.',
            'max_score' => 20,
            'weight' => 10,
            'due_at' => now()->addDays(3),
            'status' => $status,
            'published_at' => $status === 'published' ? now() : null,
        ]);
    }
}
