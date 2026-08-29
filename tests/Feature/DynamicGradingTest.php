<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicGradingTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_weights_are_transmuted_to_the_one_to_five_scale(): void
    {
        [$facilitator, $student, $section] = $this->records();
        $types = ['activity', 'project', 'exam', 'quiz'];

        foreach ($types as $type) {
            $assessment = Assessment::create([
                'section_id' => $section->id, 'created_by' => $facilitator->id,
                'title' => ucfirst($type), 'type' => $type, 'max_score' => 100,
                'weight' => 25, 'status' => 'published', 'published_at' => now(),
            ]);
            AssessmentSubmission::create([
                'assessment_id' => $assessment->id, 'student_id' => $student->id,
                'submitted_at' => now(), 'score' => 75, 'graded_by' => $facilitator->id, 'graded_at' => now(),
            ]);
        }

        $summary = app(GradeService::class)->summary($student, $section->id);
        $this->assertSame(75.0, $summary['percentage']);
        $this->assertSame(3.0, $summary['grade']);

        AssessmentSubmission::where('student_id', $student->id)->update(['score' => 100]);
        $this->assertSame(1.0, app(GradeService::class)->summary($student, $section->id)['grade']);

        AssessmentSubmission::where('student_id', $student->id)->update(['score' => 74]);
        $this->assertSame(5.0, app(GradeService::class)->summary($student, $section->id)['grade']);
    }

    public function test_facilitator_can_edit_a_score_from_the_gradebook(): void
    {
        [$facilitator, $student, $section] = $this->records();
        app(GradeService::class)->summary($student, $section->id);
        $category = $section->gradingCategories()->where('name', 'Quizzes')->firstOrFail();
        $assessment = Assessment::create([
            'section_id' => $section->id, 'grading_category_id' => $category->id,
            'created_by' => $facilitator->id, 'title' => 'Quiz 1', 'type' => 'quiz',
            'max_score' => 50, 'weight' => 20, 'status' => 'published', 'published_at' => now(),
        ]);

        $this->actingAs($facilitator)->putJson('/facilitator/grades/'.$section->id.'/scores', [
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 45,
        ])->assertOk()->assertJsonPath('percentage', 18)->assertJsonPath('grade', 5);

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 45,
        ]);
    }

    private function records(): array
    {
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        return [$facilitator, $student, $section];
    }
}
