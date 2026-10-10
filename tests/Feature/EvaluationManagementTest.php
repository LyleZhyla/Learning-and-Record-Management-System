<?php

namespace Tests\Feature;

use App\Models\CommunityProject;
use App\Models\EvaluationResponse;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_evaluate_assigned_instructor_once_and_update_response(): void
    {
        [$component, $section, $student, $facilitator] = $this->sectionWorkspace();

        $this->actingAs($student)->get('/student/evaluations')
            ->assertOk()->assertSee($facilitator->name)->assertSee('Evaluate your instructor');
        $this->actingAs($student)->post('/student/evaluations/instructor', $this->ratings('student_instructor', 5, 'Clear and supportive.'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($student)->post('/student/evaluations/instructor', $this->ratings('student_instructor', 4, 'Updated response.'))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, EvaluationResponse::where('type', 'student_instructor')->count());
        $evaluation = EvaluationResponse::firstOrFail();
        $this->assertSame($section->id, $evaluation->section_id);
        $this->assertSame($student->id, $evaluation->evaluator_id);
        $this->assertSame($facilitator->id, $evaluation->subject_user_id);
        $this->assertSame(4.0, $evaluation->averageRating());
    }

    public function test_facilitator_can_evaluate_enrolled_student_and_student_can_read_feedback(): void
    {
        [$component, $section, $student, $facilitator] = $this->sectionWorkspace();

        $this->actingAs($facilitator)->put('/facilitator/evaluations/sections/'.$section->id.'/students/'.$student->id, $this->ratings('instructor_student', 5, 'Consistently responsible and engaged.'))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($student)->get('/student/evaluations')
            ->assertOk()->assertSee('Consistently responsible and engaged.')->assertSee('5.00/5');
        $this->actingAs($facilitator)->get('/facilitator/evaluations')
            ->assertOk()->assertSee($student->name)->assertSee('Consistently responsible and engaged.');

        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->actingAs($otherFacilitator)->put('/facilitator/evaluations/sections/'.$section->id.'/students/'.$student->id, $this->ratings('instructor_student', 3))
            ->assertForbidden();
    }

    public function test_community_beneficiary_can_submit_feedback_through_open_project_survey(): void
    {
        [$component, $section, $student, $facilitator] = $this->sectionWorkspace();
        $project = $this->approvedProject($component, $section, $student);

        $this->get('/community-feedback/'.$project->id.'/'.$project->feedback_token)->assertNotFound();
        $this->actingAs($facilitator)->put('/facilitator/evaluations/community-projects/'.$project->id.'/survey', [
            'feedback_is_open' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $project->refresh();
        $this->get('/community-feedback/'.$project->id.'/wrong-token')->assertNotFound();
        $this->get('/community-feedback/'.$project->id.'/'.$project->feedback_token)
            ->assertOk()->assertSee('Community Feedback Survey')->assertSee($project->title);
        $this->post('/community-feedback/'.$project->id.'/'.$project->feedback_token, [
            ...$this->ratings('community_feedback', 5, 'The literacy sessions were useful.'),
            'respondent_name' => 'Community Partner',
            'respondent_relationship' => 'Barangay representative',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('evaluation_responses', [
            'type' => 'community_feedback',
            'community_project_id' => $project->id,
            'respondent_relationship' => 'Barangay representative',
        ]);
        $this->actingAs($facilitator)->get('/facilitator/evaluations')
            ->assertOk()->assertSee('Community Partner')->assertSee('The literacy sessions were useful.');
    }

    public function test_coordinator_evaluation_dashboard_is_limited_to_assigned_component(): void
    {
        [$component, $section, $student] = $this->sectionWorkspace();
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $otherComponent = NstpComponent::create(['code' => 'LTS', 'name' => 'LTS', 'is_active' => true]);
        $otherSection = NstpSection::create([
            'component_id' => $otherComponent->id,
            'code' => 'LTS-HIDDEN',
            'name' => 'Hidden LTS Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);

        $this->actingAs($coordinator)->get('/coordinator/evaluations')
            ->assertOk()->assertSee($section->code)->assertDontSee($otherSection->code);
    }

    private function sectionWorkspace(): array
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-EVAL-01',
            'name' => 'CWTS Evaluation Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        return [$component, $section, $student, $facilitator];
    }

    private function approvedProject(NstpComponent $component, NstpSection $section, User $student): CommunityProject
    {
        return CommunityProject::create([
            'reference_number' => 'CP-2026-EVAL-01',
            'title' => 'Community Literacy Evaluation Project',
            'component_id' => $component->id,
            'section_id' => $section->id,
            'proposed_by' => $student->id,
            'description' => 'Literacy service activity.',
            'objectives' => 'Improve access to guided reading.',
            'beneficiaries' => 'Barangay learners',
            'location' => 'Barangay Learning Center',
            'budget' => 2500,
            'approval_status' => 'approved',
            'implementation_status' => 'ongoing',
        ]);
    }

    private function ratings(string $type, int $score, ?string $comments = null): array
    {
        return [
            'ratings' => array_fill_keys(array_keys(EvaluationResponse::CRITERIA[$type]), $score),
            'comments' => $comments,
        ];
    }
}
