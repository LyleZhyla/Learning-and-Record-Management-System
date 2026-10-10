<?php

namespace Tests\Feature;

use App\Models\FacilitatorRequirement;
use App\Models\FacilitatorRequirementSubmission;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FacilitatorRequirementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_requirement_and_facilitator_can_submit_private_file(): void
    {
        Storage::fake('local');
        [$component, $facilitator] = $this->facilitatorWorkspace('CWTS');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($admin)->post('/admin/facilitator-requirements', [
            'title' => 'Appointment or Designation',
            'description' => 'Proof of current NSTP facilitator assignment.',
            'instructions' => 'Upload a signed PDF copy.',
            'component_id' => $component->id,
            'accepted_extensions' => ['pdf'],
            'max_size_mb' => 5,
            'is_required' => 1,
            'is_active' => 1,
            'sort_order' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $requirement = FacilitatorRequirement::firstOrFail();
        $this->actingAs($facilitator)->get('/facilitator/facilitator-requirements')
            ->assertOk()->assertSee('Appointment or Designation')->assertSee('0%');
        $this->actingAs($facilitator)->post('/facilitator/facilitator-requirements/'.$requirement->id.'/submit', [
            'file' => UploadedFile::fake()->create('appointment.pdf', 100, 'application/pdf'),
            'facilitator_notes' => 'Signed appointment document.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $submission = FacilitatorRequirementSubmission::firstOrFail();
        $this->assertSame('pending', $submission->status);
        Storage::disk('local')->assertExists($submission->file_path);
        $this->actingAs($facilitator)->get('/facilitator/facilitator-requirement-submissions/'.$submission->id.'/download')
            ->assertOk()->assertDownload('appointment.pdf');

        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->actingAs($otherFacilitator)->get('/facilitator/facilitator-requirement-submissions/'.$submission->id.'/download')->assertForbidden();
    }

    public function test_correction_resubmission_and_verification_update_compliance(): void
    {
        Storage::fake('local');
        [$component, $facilitator] = $this->facilitatorWorkspace('LTS');
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $requirement = $this->requirement($component);

        $this->actingAs($facilitator)->post('/facilitator/facilitator-requirements/'.$requirement->id.'/submit', [
            'file' => UploadedFile::fake()->create('credentials.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $submission = FacilitatorRequirementSubmission::firstOrFail();
        $oldPath = $submission->file_path;

        $this->actingAs($admin)->put('/nstp-admin/facilitator-requirement-submissions/'.$submission->id.'/review', [
            'status' => 'needs_correction',
            'review_notes' => 'Upload the complete signed pages.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($facilitator)->get('/facilitator/facilitator-requirements')
            ->assertOk()->assertSee('Upload the complete signed pages.');

        $this->actingAs($facilitator)->post('/facilitator/facilitator-requirements/'.$requirement->id.'/submit', [
            'file' => UploadedFile::fake()->create('corrected-credentials.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $submission->refresh();
        $this->assertSame('pending', $submission->status);
        $this->assertNull($submission->review_notes);
        Storage::disk('local')->assertMissing($oldPath);

        $this->actingAs($admin)->put('/nstp-admin/facilitator-requirement-submissions/'.$submission->id.'/review', [
            'status' => 'verified',
            'review_notes' => 'Complete and valid.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($facilitator)->get('/facilitator/facilitator-requirements')
            ->assertOk()->assertSee('100%')->assertSee('Verified');
    }

    public function test_coordinator_can_only_review_facilitators_in_assigned_component(): void
    {
        Storage::fake('local');
        [$cwts, $cwtsFacilitator] = $this->facilitatorWorkspace('CWTS');
        [$rotc, $rotcFacilitator] = $this->facilitatorWorkspace('ROTC');
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $cwts->id]);
        $globalRequirement = $this->requirement(null, 'Valid Professional ID');
        $rotcRequirement = $this->requirement($rotc, 'ROTC Training Certificate');
        $cwtsSubmission = $this->submission($globalRequirement, $cwtsFacilitator, 'cwts-id.pdf');
        $rotcSubmission = $this->submission($rotcRequirement, $rotcFacilitator, 'rotc-certificate.pdf');

        $this->actingAs($coordinator)->get('/coordinator/facilitator-requirements')
            ->assertOk()->assertSee($cwtsFacilitator->name)->assertDontSee($rotcFacilitator->name);
        $this->actingAs($coordinator)->put('/coordinator/facilitator-requirement-submissions/'.$cwtsSubmission->id.'/review', [
            'status' => 'verified',
            'review_notes' => 'Verified within component.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($coordinator)->put('/coordinator/facilitator-requirement-submissions/'.$rotcSubmission->id.'/review', [
            'status' => 'verified',
        ])->assertForbidden();
    }

    private function facilitatorWorkspace(string $code): array
    {
        $component = NstpComponent::create(['code' => $code, 'name' => $code, 'is_active' => true]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => $code.'-REQ-'.uniqid(),
            'name' => $code.' Requirement Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);

        return [$component, $facilitator];
    }

    private function requirement(?NstpComponent $component, string $title = 'Facilitator Credentials'): FacilitatorRequirement
    {
        return FacilitatorRequirement::create([
            'title' => $title,
            'slug' => FacilitatorRequirement::uniqueSlug($title),
            'component_id' => $component?->id,
            'accepted_extensions' => ['pdf'],
            'max_size_kb' => 5120,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 10,
        ]);
    }

    private function submission(FacilitatorRequirement $requirement, User $facilitator, string $filename): FacilitatorRequirementSubmission
    {
        $file = UploadedFile::fake()->create($filename, 50, 'application/pdf');
        $path = $file->store('facilitator-requirements/'.$facilitator->id, 'local');

        return FacilitatorRequirementSubmission::create([
            'facilitator_requirement_id' => $requirement->id,
            'facilitator_id' => $facilitator->id,
            'file_path' => $path,
            'original_name' => $filename,
            'mime_type' => 'application/pdf',
            'size_bytes' => 50 * 1024,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }
}
