<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnifiedProjectAssessmentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilitator_and_coordinator_are_the_only_roles_that_can_choose_and_create_assessment_types(): void
    {
        $component = $this->makeComponent();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $component->id,
        ]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $section = $this->section($component, $facilitator);
        app(GradeService::class)->ensureStructure($section);
        $projectCategory = $section->gradingCategories()->where('assessment_type', 'project')->firstOrFail();

        $this->actingAs($coordinator)->get(route('coordinator.assessments.index'))
            ->assertOk()->assertSee('Create assessment')->assertSee('Project');
        $this->actingAs($coordinator)->get(route('coordinator.assessments.create'))
            ->assertOk()->assertSee('Assessment type')->assertSee('Project files will be submitted');
        $this->actingAs($facilitator)->get(route('facilitator.assessments.create'))->assertOk();
        $this->actingAs($superAdmin)->get(route('admin.assessments.create'))->assertForbidden();
        $this->actingAs($nstpAdmin)->get(route('nstp_admin.assessments.create'))->assertForbidden();

        $this->actingAs($coordinator)->post(route('coordinator.assessments.store'), [
            'section_id' => $section->id,
            'grading_category_id' => $projectCategory->id,
            'title' => 'Community Development Project',
            'type' => 'project',
            'instructions' => 'Submit the completed project document.',
            'max_score' => 100,
            'status' => 'published',
            'create_answer_sheet' => '0',
            'rubric_criteria' => [[
                'title' => 'Project quality',
                'description' => 'Completeness and quality of the submitted project.',
                'percentage' => 100,
                'score' => 100,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'title' => 'Community Development Project',
            'type' => 'project',
            'created_by' => $coordinator->id,
        ]);
    }

    public function test_student_submits_a_project_file_through_assessments_only(): void
    {
        Storage::fake('local');
        $component = $this->makeComponent();
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $section = $this->section($component, $facilitator);
        app(GradeService::class)->ensureStructure($section);
        $projectCategory = $section->gradingCategories()->where('assessment_type', 'project')->firstOrFail();
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
        $assessment = Assessment::create([
            'section_id' => $section->id,
            'grading_category_id' => $projectCategory->id,
            'created_by' => $facilitator->id,
            'title' => 'Project Portfolio',
            'type' => 'project',
            'instructions' => 'Upload the final portfolio.',
            'max_score' => 100,
            'weight' => $projectCategory->weight,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($student)->get(route('student.assessments.show', $assessment))
            ->assertOk()->assertSee('Attach project file')->assertSee('Required for first submission');
        $this->actingAs($student)->post(route('student.assessments.submit', $assessment), [
            'answer_text' => 'Project notes only.',
        ])->assertSessionHasErrors('file');

        $this->actingAs($student)->post(route('student.assessments.submit', $assessment), [
            'answer_text' => 'Final project portfolio.',
            'file' => UploadedFile::fake()->create('project-portfolio.pdf', 500, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Your project was submitted successfully through Assessments.');

        $this->assertDatabaseHas('assessment_submissions', [
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
            'original_filename' => 'project-portfolio.pdf',
        ]);
    }

    private function makeComponent(): NstpComponent
    {
        return NstpComponent::create([
            'code' => 'LTS',
            'name' => 'Literacy Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
    }

    private function section(NstpComponent $component, User $facilitator): NstpSection
    {
        return NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'LTS-01',
            'name' => 'LTS Section 01',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
    }
}
