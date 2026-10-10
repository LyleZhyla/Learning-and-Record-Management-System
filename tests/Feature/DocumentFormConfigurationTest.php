<?php

namespace Tests\Feature;

use App\Models\DocumentForm;
use App\Models\DocumentSubmission;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentFormConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_component_specific_document_requirement(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'is_active' => true]);

        $this->actingAs($admin)->post('/admin/document-forms', [
            'title' => 'ROTC Medical Clearance',
            'category' => 'form',
            'description' => 'Official medical clearance form.',
            'instructions' => 'Download, complete, sign, and upload the form.',
            'component_id' => $rotc->id,
            'accepted_extensions' => ['pdf', 'jpg'],
            'max_size_mb' => 5,
            'requires_submission' => 1,
            'is_required' => 1,
            'template' => UploadedFile::fake()->create('medical-clearance.pdf', 50, 'application/pdf'),
            'is_active' => 1,
            'sort_order' => 10,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $form = DocumentForm::firstOrFail();
        $this->assertSame($rotc->id, $form->component_id);
        $this->assertSame(['pdf', 'jpg'], $form->accepted_extensions);
        $this->assertSame(5120, $form->max_size_kb);
        Storage::disk('local')->assertExists($form->template_path);
        $this->actingAs($admin)->get('/admin/document-forms')->assertOk()->assertSee('ROTC Medical Clearance');
    }

    public function test_students_only_see_documents_for_their_component_and_can_submit_valid_files(): void
    {
        Storage::fake('local');
        [$student, $cwts] = $this->enrolledStudent();
        $visible = $this->documentForm(['title' => 'CWTS Project Consent', 'component_id' => $cwts->id]);
        $hiddenComponent = NstpComponent::create(['code' => 'ROTC', 'name' => 'ROTC', 'is_active' => true]);
        $this->documentForm(['title' => 'ROTC Proof', 'component_id' => $hiddenComponent->id]);

        $this->actingAs($student)->get('/student/documents')
            ->assertOk()
            ->assertSee('CWTS Project Consent')
            ->assertDontSee('ROTC Proof');

        $this->actingAs($student)->post('/student/documents/'.$visible->id, [
            'file' => UploadedFile::fake()->create('consent.pdf', 100, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $submission = DocumentSubmission::firstOrFail();
        $this->assertSame('pending', $submission->status);
        $this->assertSame('2026-2027', $submission->academic_year);
        Storage::disk('local')->assertExists($submission->file_path);
    }

    public function test_student_upload_validation_and_admin_review_flow_are_enforced(): void
    {
        Storage::fake('local');
        [$student] = $this->enrolledStudent();
        $form = $this->documentForm(['accepted_extensions' => ['pdf'], 'max_size_kb' => 1024]);

        $this->actingAs($student)->post('/student/documents/'.$form->id, [
            'file' => UploadedFile::fake()->image('invalid.png'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($student)->post('/student/documents/'.$form->id, [
            'file' => UploadedFile::fake()->create('valid.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $submission = DocumentSubmission::firstOrFail();
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($admin)->get('/nstp-admin/document-reviews')
            ->assertOk()
            ->assertSee('Certificate of Registration')
            ->assertSee($student->name);

        $this->actingAs($admin)->patch('/nstp-admin/document-reviews/'.$submission->id, [
            'status' => 'needs_correction',
            'review_notes' => '',
        ])->assertSessionHasErrors('review_notes');

        $this->actingAs($admin)->patch('/nstp-admin/document-reviews/'.$submission->id, [
            'status' => 'verified',
            'review_notes' => 'Complete and readable.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('verified', $submission->fresh()->status);
        $this->actingAs($student)->post('/student/documents/'.$form->id, [
            'file' => UploadedFile::fake()->create('replacement.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_students_cannot_download_another_students_submission(): void
    {
        Storage::fake('local');
        [$owner] = $this->enrolledStudent();
        [$other] = $this->enrolledStudent('other@example.test');
        $form = $this->documentForm();
        $path = UploadedFile::fake()->create('private.pdf', 20, 'application/pdf')->store('configurable-document-submissions', 'local');
        $submission = DocumentSubmission::create([
            'document_form_id' => $form->id,
            'user_id' => $owner->id,
            'file_path' => $path,
            'original_filename' => 'private.pdf',
            'status' => 'pending',
        ]);

        $this->actingAs($other)->get('/student/documents/submissions/'.$submission->id)->assertForbidden();
    }

    /** @return array{User, NstpComponent} */
    private function enrolledStudent(string $email = 'student@example.test'): array
    {
        $student = User::factory()->create(['email' => $email, 'role' => 'student', 'status' => 'active']);
        $component = NstpComponent::firstOrCreate(['code' => 'CWTS'], ['name' => 'Civic Welfare Training Service', 'is_active' => true]);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        return [$student, $component];
    }

    /** @param array<string, mixed> $attributes */
    private function documentForm(array $attributes = []): DocumentForm
    {
        return DocumentForm::create(array_merge([
            'title' => 'Certificate of Registration',
            'slug' => 'certificate-of-registration-'.uniqid(),
            'category' => 'document',
            'accepted_extensions' => ['pdf'],
            'max_size_kb' => 5120,
            'requires_submission' => true,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }
}
