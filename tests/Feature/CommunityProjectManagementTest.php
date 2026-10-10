<?php

namespace Tests\Feature;

use App\Models\CommunityProject;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_a_scoped_project_with_beneficiaries_location_and_budget(): void
    {
        [$component, $section, $student] = $this->enrolledStudent();
        $otherComponent = NstpComponent::create(['code' => 'ROTC', 'name' => 'ROTC', 'is_active' => true]);

        $this->actingAs($student)->post('/student/community-projects', $this->projectData([
            'component_id' => $otherComponent->id,
            'section_id' => null,
        ]))->assertRedirect();

        $project = CommunityProject::firstOrFail();
        $this->assertSame($component->id, $project->component_id);
        $this->assertSame($section->id, $project->section_id);
        $this->assertSame($student->id, $project->proposed_by);
        $this->assertSame('pending', $project->approval_status);
        $this->assertSame('proposed', $project->implementation_status);
        $this->assertSame('CP-'.now()->format('Y').'-00001', $project->reference_number);
        $this->assertSame('Barangay learners and parents', $project->beneficiaries);
        $this->assertSame('Barangay Malacampa Learning Center', $project->location);
        $this->assertSame('15000.00', $project->budget);
    }

    public function test_coordinator_can_approve_component_project_and_facilitator_can_track_activities(): void
    {
        [$component, $section, $student, $facilitator] = $this->enrolledStudent();
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $component->id,
        ]);
        $project = CommunityProject::create([
            ...$this->projectData(),
            'reference_number' => 'CP-2026-00001',
            'component_id' => $component->id,
            'section_id' => $section->id,
            'proposed_by' => $student->id,
        ]);

        $this->actingAs($student)->put('/student/community-projects/'.$project->id.'/approval', [
            'approval_status' => 'approved',
        ])->assertForbidden();

        $this->actingAs($coordinator)->put('/coordinator/community-projects/'.$project->id.'/approval', [
            'approval_status' => 'approved',
            'approval_notes' => 'Community need and safeguards verified.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('community_projects', [
            'id' => $project->id,
            'approval_status' => 'approved',
            'implementation_status' => 'planning',
            'approved_by' => $coordinator->id,
        ]);

        $this->actingAs($facilitator)->post('/facilitator/community-projects/'.$project->id.'/activities', [
            'title' => 'Community needs validation',
            'description' => 'Meet local partners and verify baseline needs.',
            'scheduled_date' => '2026-11-05',
            'status' => 'completed',
            'accomplishment_notes' => 'Consultation completed with 25 intended beneficiaries.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($facilitator)->put('/facilitator/community-projects/'.$project->id.'/implementation', [
            'implementation_status' => 'ongoing',
            'implementation_notes' => 'Delivery activities started.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('community_project_activities', [
            'community_project_id' => $project->id,
            'title' => 'Community needs validation',
            'status' => 'completed',
        ]);
        $this->assertSame('ongoing', $project->fresh()->implementation_status);
    }

    public function test_project_visibility_is_limited_by_role_scope(): void
    {
        [$component, $section, $student] = $this->enrolledStudent();
        $otherComponent = NstpComponent::create(['code' => 'LTS', 'name' => 'LTS', 'is_active' => true]);
        $otherCoordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $otherComponent->id,
        ]);
        $project = CommunityProject::create([
            ...$this->projectData(),
            'reference_number' => 'CP-2026-00002',
            'component_id' => $component->id,
            'section_id' => $section->id,
            'proposed_by' => $student->id,
        ]);

        $this->actingAs($otherCoordinator)->get('/coordinator/community-projects/'.$project->id)->assertForbidden();
        $this->actingAs($otherCoordinator)->get('/coordinator/community-projects')->assertOk()->assertDontSee($project->title);
        $this->actingAs($student)->get('/student/community-projects/'.$project->id)->assertOk()->assertSee($project->title);
    }

    public function test_each_authorized_portal_can_open_its_community_project_workspace(): void
    {
        [$component, $section, $student, $facilitator] = $this->enrolledStudent();
        $users = [
            ['/admin/community-projects', User::factory()->create(['role' => 'super_admin', 'status' => 'active'])],
            ['/nstp-admin/community-projects', User::factory()->create(['role' => 'nstp_admin', 'status' => 'active'])],
            ['/coordinator/community-projects', User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id])],
            ['/facilitator/community-projects', $facilitator],
            ['/student/community-projects', $student],
        ];

        foreach ($users as [$path, $user]) {
            $this->actingAs($user)->get($path)->assertOk()->assertSee('Community Project Management');
        }
    }

    private function enrolledStudent(): array
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-01',
            'name' => 'CWTS Section 1',
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

    private function projectData(array $overrides = []): array
    {
        return [
            'title' => 'Community Literacy and Learning Hub',
            'description' => 'Establish a weekend learning support activity based on verified community needs.',
            'objectives' => "Assess learner needs\nDeliver four guided literacy sessions\nMeasure participation and outcomes",
            'beneficiaries' => 'Barangay learners and parents',
            'beneficiary_count' => 50,
            'location' => 'Barangay Malacampa Learning Center',
            'budget' => 15000,
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-15',
            ...$overrides,
        ];
    }
}
