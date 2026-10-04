<?php

namespace Tests\Feature;

use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationDocumentReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_nstp_and_super_admin_can_review_and_open_submitted_documents(): void
    {
        Storage::fake('local');
        $registration = $this->registration();

        foreach (['nstp_admin' => 'nstp_admin', 'super_admin' => 'admin'] as $role => $prefix) {
            $admin = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($admin)
                ->get(route($prefix.'.registrations.index'))
                ->assertOk()
                ->assertSee($registration->reference_code)
                ->assertSee('2 of 2 files available');

            $this->actingAs($admin)
                ->get(route($prefix.'.registrations.show', $registration))
                ->assertOk()
                ->assertSee('certificate-of-registration.pdf')
                ->assertSee('formal-photo.jpg')
                ->assertSee('Document checklist');

            $this->actingAs($admin)
                ->get(route($prefix.'.registrations.documents.show', [$registration, 'cor']))
                ->assertOk()
                ->assertHeader('content-disposition', 'inline; filename=certificate-of-registration.pdf');
        }
    }

    public function test_both_documents_must_be_verified_for_the_registration_to_be_document_verified(): void
    {
        Storage::fake('local');
        $registration = $this->registration();
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('nstp_admin.registrations.review', $registration), [
                'cor_review_status' => 'verified',
                'formal_photo_review_status' => 'verified',
                'review_notes' => 'The submitted details match both readable files.',
            ])
            ->assertRedirect(route('nstp_admin.registrations.show', $registration))
            ->assertSessionHasNoErrors();

        $registration->refresh();
        $this->assertSame('verified', $registration->status);
        $this->assertSame('verified', $registration->cor_review_status);
        $this->assertSame($admin->id, $registration->reviewed_by);
        $this->assertNotNull($registration->reviewed_at);
    }

    public function test_missing_file_cannot_be_verified_and_correction_requires_notes(): void
    {
        Storage::fake('local');
        $registration = $this->registration();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        Storage::disk('local')->delete($registration->cor_path);

        $this->actingAs($admin)
            ->from(route('admin.registrations.show', $registration))
            ->patch(route('admin.registrations.review', $registration), [
                'cor_review_status' => 'verified',
                'formal_photo_review_status' => 'verified',
            ])
            ->assertSessionHasErrors('cor_review_status');

        $this->actingAs($admin)
            ->from(route('admin.registrations.show', $registration))
            ->patch(route('admin.registrations.review', $registration), [
                'cor_review_status' => 'needs_correction',
                'formal_photo_review_status' => 'verified',
            ])
            ->assertSessionHasErrors('review_notes');

        $this->actingAs($admin)
            ->patch(route('admin.registrations.review', $registration), [
                'cor_review_status' => 'needs_correction',
                'formal_photo_review_status' => 'verified',
                'review_notes' => 'Please upload the missing COR again.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('student_registrations', [
            'id' => $registration->id,
            'status' => 'needs_correction',
            'review_notes' => 'Please upload the missing COR again.',
        ]);
    }

    public function test_other_roles_cannot_access_registration_reviews(): void
    {
        Storage::fake('local');
        $registration = $this->registration();
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->actingAs($facilitator)->get('/admin/registrations')->assertForbidden();
        $this->actingAs($facilitator)->get('/nstp-admin/registrations')->assertForbidden();
        $this->actingAs($facilitator)->get('/admin/registrations/'.$registration->id.'/documents/cor')->assertForbidden();
    }

    private function registration(): StudentRegistration
    {
        $cor = UploadedFile::fake()->createWithContent('certificate-of-registration.pdf', "%PDF-1.4\nNSTP registration test document");
        $photo = UploadedFile::fake()->image('formal-photo.jpg', 600, 800);

        return StudentRegistration::create([
            'reference_code' => 'NSTP-2026-TEST0001',
            'status' => 'pending',
            'cor_path' => $cor->store('student-registrations/cor', 'local'),
            'cor_original_name' => $cor->getClientOriginalName(),
            'formal_photo_path' => $photo->store('student-registrations/formal-photos', 'local'),
            'formal_photo_original_name' => $photo->getClientOriginalName(),
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'province' => 'Tarlac',
            'city_municipality' => 'Tarlac City',
            'barangay' => 'San Vicente',
            'date_of_birth' => '2007-04-15',
            'birth_province' => 'Tarlac',
            'birth_city_municipality' => 'Tarlac City',
            'religion' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09123456789',
            'email' => 'juan.registration@example.test',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09987654321',
            'emergency_same_address' => true,
            'student_number' => '2026123456',
            'college' => 'College of Education',
            'course' => 'Bachelor of Secondary Education (BSEd)',
            'major' => 'Mathematics',
            'year_section' => '1A',
        ]);
    }
}
