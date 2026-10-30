<?php

namespace Tests\Feature;

use App\Models\CommunityNeedPriority;
use App\Models\CommunityNeedsAssessment;
use App\Models\CommunityPartner;
use App\Models\CommunityProject;
use App\Models\Institution;
use App\Models\NstpComponent;
use App\Models\PartnershipDocument;
use App\Models\ProjectImpactIndicator;
use App\Models\User;
use App\Services\InstitutionProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityPartnerNeedsRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_build_a_verified_partner_need_registry_with_private_agreement(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/community-registry/partners', $this->partnerData())
            ->assertRedirect()->assertSessionHasNoErrors();
        $partner = CommunityPartner::firstOrFail();
        $this->assertSame('pending', $partner->verification_status);

        $this->actingAs($admin)->put("/admin/community-registry/partners/{$partner->id}/verification", [
            'verification_status' => 'verified',
            'verification_notes' => 'Identity and authority verified with the barangay office.',
        ])->assertRedirect();

        $this->actingAs($admin)->post("/admin/community-registry/partners/{$partner->id}/documents", [
            'document_type' => 'moa',
            'title' => 'NSTP Community Partnership MOA',
            'reference_number' => 'MOA-2026-001',
            'effective_date' => '2026-01-01',
            'expiration_date' => '2027-12-31',
            'file' => UploadedFile::fake()->create('partnership-moa.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $document = PartnershipDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($admin)->post("/admin/community-registry/partners/{$partner->id}/assessments", [
            'title' => 'Barangay Learner Needs Assessment 2026',
            'assessed_on' => '2026-10-01',
            'location' => 'Barangay Malacampa Learning Center',
            'beneficiary_group' => 'Out-of-school youth and struggling readers',
            'estimated_beneficiaries' => 80,
            'assessment_methods' => 'Household survey, focus-group discussion, and reading observation.',
            'findings' => 'Learners need structured reading support and access to age-appropriate materials.',
        ])->assertRedirect();
        $assessment = CommunityNeedsAssessment::firstOrFail();
        $this->actingAs($admin)->put("/admin/community-registry/partners/{$partner->id}/assessments/{$assessment->id}", [
            'status' => 'verified',
        ])->assertRedirect();

        $this->actingAs($admin)->post("/admin/community-registry/partners/{$partner->id}/assessments/{$assessment->id}/priorities", [
            'problem_title' => 'Low functional reading proficiency',
            'problem_statement' => 'Many intended learners remain below the expected reading benchmark.',
            'category' => 'education',
            'priority_level' => 'high',
            'sdg_codes' => ['SDG4', 'SDG10', 'SDG17'],
            'location' => 'Barangay Malacampa Learning Center',
            'estimated_budget' => 20000,
            'required_skills_text' => 'Teaching and facilitation, Literacy and numeracy, Research and data collection',
            'recommended_response' => 'Run a baseline-tested weekend literacy intervention.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $priority = CommunityNeedPriority::firstOrFail();
        $this->assertSame(['SDG4', 'SDG10', 'SDG17'], $priority->sdg_codes);
        $this->assertContains('Literacy and numeracy', $priority->required_skills);
        $this->actingAs($admin)->get("/admin/community-registry/partners/{$partner->id}")
            ->assertOk()->assertSee('MOA-2026-001')->assertSee('Low functional reading proficiency');
        $this->actingAs($admin)->get("/admin/community-registry/partners/{$partner->id}/documents/{$document->id}")
            ->assertDownload('partnership-moa.pdf');
    }

    public function test_project_is_scored_against_verified_need_location_budget_and_skills(): void
    {
        $admin = $this->admin();
        [$partner, $priority] = $this->verifiedNeed($admin);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/community-projects', $this->projectData($component, $priority))
            ->assertRedirect()->assertSessionHasNoErrors();

        $project = CommunityProject::firstOrFail();
        $this->assertSame($partner->id, $project->community_partner_id);
        $this->assertSame($priority->id, $project->community_need_priority_id);
        $this->assertSame(100, $project->need_match_score);
        $this->assertSame(25, data_get($project->need_match_snapshot, 'location.score'));
        $this->assertSame(25, data_get($project->need_match_snapshot, 'budget.score'));
        $this->assertSame(30, data_get($project->need_match_snapshot, 'skills.score'));
        $this->assertSame('matched', $priority->fresh()->status);

        $this->actingAs($admin)->get("/admin/community-projects/{$project->id}")
            ->assertOk()->assertSee('100% match')->assertSee($partner->name);
    }

    public function test_unverified_assessment_cannot_be_used_to_match_a_project(): void
    {
        $admin = $this->admin();
        $partner = CommunityPartner::create($this->partnerAttributes(['verification_status' => 'verified', 'verified_at' => now()]));
        $assessment = $partner->assessments()->create([
            'title' => 'Draft assessment', 'assessed_on' => today(), 'location' => 'Draft location',
            'beneficiary_group' => 'Residents', 'assessment_methods' => 'Interview', 'findings' => 'Draft findings',
            'status' => 'draft', 'created_by' => $admin->id,
        ]);
        $priority = $assessment->priorities()->create([
            'problem_title' => 'Unverified need', 'problem_statement' => 'Still being validated.',
            'category' => 'education', 'priority_level' => 'medium', 'sdg_codes' => ['SDG4'],
            'location' => 'Draft location', 'required_skills' => [], 'status' => 'open',
        ]);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/community-projects', $this->projectData($component, $priority))
            ->assertStatus(422);
        $this->assertDatabaseCount('community_projects', 0);
    }

    public function test_manager_records_baseline_and_post_project_impact(): void
    {
        $admin = $this->admin();
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);
        $project = CommunityProject::create([
            'reference_number' => 'CP-2026-0001', 'title' => 'Literacy Project', 'component_id' => $component->id,
            'proposed_by' => $admin->id, 'description' => 'Project', 'objectives' => 'Improve reading',
            'beneficiaries' => 'Learners', 'beneficiary_count' => 50, 'location' => 'Learning Center',
            'budget' => 10000, 'approval_status' => 'approved', 'implementation_status' => 'ongoing',
        ]);

        $this->actingAs($admin)->post("/admin/community-projects/{$project->id}/impact-indicators", [
            'indicator_name' => 'Learners meeting reading benchmark', 'unit' => 'learners',
            'baseline_value' => 12, 'target_value' => 35, 'baseline_measured_on' => '2026-10-01',
            'notes' => 'Partner-administered baseline reading check.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $indicator = ProjectImpactIndicator::firstOrFail();

        $this->actingAs($admin)->put("/admin/community-projects/{$project->id}/impact-indicators/{$indicator->id}", [
            'indicator_name' => $indicator->indicator_name, 'unit' => 'learners', 'baseline_value' => 12,
            'target_value' => 35, 'post_project_value' => 39, 'baseline_measured_on' => '2026-10-01',
            'post_measured_on' => '2026-12-15', 'notes' => 'Same reading tool used after implementation.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(27.0, $indicator->fresh()->change());
        $this->actingAs($admin)->get("/admin/community-projects/{$project->id}")
            ->assertOk()->assertSee('+27.00 learners');
    }

    public function test_partner_page_aggregates_repeat_project_and_beneficiary_history(): void
    {
        $admin = $this->admin();
        [$partner, $priority] = $this->verifiedNeed($admin);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);
        foreach ([30, 45] as $index => $beneficiaries) {
            CommunityProject::create([
                'reference_number' => 'CP-2026-000'.($index + 1), 'title' => 'Repeat Project '.($index + 1),
                'component_id' => $component->id, 'community_partner_id' => $partner->id,
                'community_need_priority_id' => $priority->id, 'proposed_by' => $admin->id,
                'description' => 'Linked project', 'objectives' => 'Respond to need', 'beneficiaries' => 'Learners',
                'beneficiary_count' => $beneficiaries, 'location' => $priority->location, 'budget' => 10000,
                'approval_status' => 'approved', 'implementation_status' => $index ? 'ongoing' : 'completed',
            ]);
        }

        $this->actingAs($admin)->get("/admin/community-registry/partners/{$partner->id}")
            ->assertOk()->assertSee('2 project(s)')->assertSee('75')->assertSee('Repeat Project 1')->assertSee('Repeat Project 2');
    }

    public function test_students_see_only_verified_partners_and_cannot_manage_registry(): void
    {
        $student = User::factory()->create([
            'role' => 'student', 'status' => 'active', 'must_complete_student_profile' => false,
            'must_upload_student_documents' => false,
        ]);
        $verified = CommunityPartner::create($this->partnerAttributes(['name' => 'Verified Barangay', 'verification_status' => 'verified', 'verified_at' => now()]));
        CommunityPartner::create($this->partnerAttributes(['name' => 'Pending Barangay', 'verification_status' => 'pending']));

        $this->actingAs($student)->get('/student/community-registry')
            ->assertOk()->assertSee($verified->name)->assertDontSee('Pending Barangay')->assertDontSee('Partner onboarding');
        $this->actingAs($student)->post('/student/community-registry/partners', $this->partnerData(['name' => 'Unauthorized']))
            ->assertForbidden();
    }

    public function test_partner_registry_is_isolated_between_institutions(): void
    {
        $admin = $this->admin();
        $sourceInstitutionId = Institution::query()->firstOrFail()->id;
        $other = Institution::create([
            'name' => 'Other University', 'short_name' => 'OU', 'slug' => 'other-university', 'code' => 'OU', 'is_active' => true,
        ]);
        app(InstitutionProvisioner::class)->provision($other, $sourceInstitutionId);
        $foreignPartner = CommunityPartner::withoutGlobalScope('institution')->create(
            $this->partnerAttributes(['name' => 'Foreign Partner', 'institution_id' => $other->id, 'verification_status' => 'verified'])
        );

        $this->actingAs($admin)->get("/admin/community-registry/partners/{$foreignPartner->id}")->assertNotFound();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    }

    private function verifiedNeed(User $admin): array
    {
        $partner = CommunityPartner::create($this->partnerAttributes(['verification_status' => 'verified', 'verified_by' => $admin->id, 'verified_at' => now()]));
        $assessment = $partner->assessments()->create([
            'title' => 'Verified literacy needs assessment', 'assessed_on' => '2026-10-01',
            'location' => 'Barangay Malacampa Learning Center', 'beneficiary_group' => 'Learners',
            'estimated_beneficiaries' => 80, 'assessment_methods' => 'Survey and reading check',
            'findings' => 'Reading support is the highest priority.', 'status' => 'verified',
            'created_by' => $admin->id, 'verified_by' => $admin->id, 'verified_at' => now(),
        ]);
        $priority = $assessment->priorities()->create([
            'problem_title' => 'Low functional reading proficiency', 'problem_statement' => 'Learners are below benchmark.',
            'category' => 'education', 'priority_level' => 'high', 'sdg_codes' => ['SDG4', 'SDG10'],
            'location' => 'Barangay Malacampa Learning Center', 'estimated_budget' => 20000,
            'required_skills' => ['Teaching and facilitation', 'Literacy and numeracy'], 'status' => 'open',
        ]);

        return [$partner, $priority];
    }

    private function partnerData(array $overrides = []): array
    {
        return [
            'name' => 'Barangay Malacampa Council', 'partner_type' => 'barangay',
            'contact_person' => 'Barangay Secretary', 'email' => 'malacampa@example.test', 'phone' => '09171234567',
            'address' => 'Barangay Malacampa, Camiling, Tarlac', 'province' => 'Tarlac',
            'city_municipality' => 'Camiling', 'barangay' => 'Malacampa',
            'service_areas_text' => 'Education, Youth development',
            'available_resources_text' => 'Learning center, Community volunteers', 'is_active' => 1,
            ...$overrides,
        ];
    }

    private function partnerAttributes(array $overrides = []): array
    {
        return [
            'name' => 'Barangay Malacampa Council', 'partner_type' => 'barangay',
            'contact_person' => 'Barangay Secretary', 'email' => 'malacampa@example.test', 'phone' => '09171234567',
            'address' => 'Barangay Malacampa, Camiling, Tarlac', 'province' => 'Tarlac',
            'city_municipality' => 'Camiling', 'barangay' => 'Malacampa',
            'service_areas' => ['Education', 'Youth development'],
            'available_resources' => ['Learning center', 'Community volunteers'], 'is_active' => true,
            ...$overrides,
        ];
    }

    private function projectData(NstpComponent $component, CommunityNeedPriority $priority): array
    {
        return [
            'title' => 'Community Literacy and Learning Hub', 'component_id' => $component->id,
            'community_need_priority_id' => $priority->id,
            'description' => 'Weekend literacy support based on the verified assessment.',
            'objectives' => 'Improve functional reading proficiency.',
            'available_skills' => ['Teaching and facilitation', 'Literacy and numeracy'],
            'beneficiaries' => 'Barangay learners', 'beneficiary_count' => 50,
            'location' => 'Barangay Malacampa Learning Center', 'budget' => 15000,
            'start_date' => '2026-11-01', 'end_date' => '2026-12-15',
        ];
    }
}
