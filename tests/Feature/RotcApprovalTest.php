<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RotcApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_rotc_request_requires_proof_and_coordinator_approval_before_enrollment(): void
    {
        Storage::fake('local');
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $rotc->id]);

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $rotc->id,
            'rotc_category' => 'MS-31',
            'shirt_size' => 'M',
        ])->assertSessionHasErrors('ms1_proof');

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $rotc->id,
            'rotc_category' => 'MS-31',
            'shirt_size' => 'M',
            'ms1_proof' => UploadedFile::fake()->create('ms1-completion.pdf', 250, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $enrollment = NstpEnrollment::where('student_id', $student->id)->firstOrFail();
        $this->assertSame('pending_approval', $enrollment->status);
        $this->assertSame('pending', $enrollment->rotc_approval_status);
        $this->assertNull($enrollment->section_id);
        Storage::disk('local')->assertExists($enrollment->rotc_proof_path);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()->assertSee('pending ROTC coordinator approval');

        $this->actingAs($coordinator)->get('/coordinator/rotc-approvals')
            ->assertOk()->assertSee($student->name)->assertSee('MS-31')->assertSee('Download proof');

        $this->actingAs($coordinator)->get('/coordinator/rotc-approvals/'.$enrollment->id.'/proof')
            ->assertOk()->assertDownload('ms1-completion.pdf');

        $this->actingAs($coordinator)->patch('/coordinator/rotc-approvals/'.$enrollment->id.'/approve')
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'id' => $enrollment->id,
            'status' => 'enrolled',
            'rotc_approval_status' => 'approved',
            'rotc_approved_by' => $coordinator->id,
        ]);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()->assertSee('ROTC enrollment is approved and awaiting section assignment');
    }

    public function test_only_the_rotc_coordinator_can_review_or_approve_requests(): void
    {
        Storage::fake('local');
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $rotcCoordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $rotc->id]);
        $cwtsCoordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $cwts->id]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $proofPath = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')->store('rotc-ms1-proofs');
        [$academicYear, $semester] = $this->currentTerm();
        $enrollment = NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $rotc->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'shirt_size' => 'L',
            'rotc_category' => 'MS-41',
            'rotc_proof_path' => $proofPath,
            'rotc_proof_original_name' => 'proof.pdf',
            'rotc_approval_status' => 'pending',
            'status' => 'pending_approval',
        ]);

        $this->actingAs($cwtsCoordinator)->get('/coordinator/rotc-approvals')->assertForbidden();
        $this->actingAs($cwtsCoordinator)->patch('/coordinator/rotc-approvals/'.$enrollment->id.'/approve')->assertForbidden();
        $this->actingAs($rotcCoordinator)->get('/coordinator/rotc-approvals')->assertOk();
    }

    /** @return array{string, string} */
    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
