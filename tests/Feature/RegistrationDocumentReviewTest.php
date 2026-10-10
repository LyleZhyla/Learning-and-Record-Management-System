<?php

namespace Tests\Feature;

use App\Jobs\SendAccountCredentials;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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
                ->assertSee('Registration review workspace')
                ->assertSee('Active review queue')
                ->assertSee('Manage registration period')
                ->assertSee($registration->reference_code)
                ->assertSee('class="registration-review-button"', false)
                ->assertSee('>Review</span>', false)
                ->assertDontSee('Review documents')
                ->assertSee('2 of 2 files available');

            $this->actingAs($admin)
                ->get(route($prefix.'.registrations.show', $registration))
                ->assertOk()
                ->assertSee('certificate-of-registration.pdf')
                ->assertSee('formal-photo.jpg')
                ->assertSee('Document checklist')
                ->assertSee('Inspect documents')
                ->assertSee('Record your decision');

            $this->actingAs($admin)
                ->get(route($prefix.'.registrations.documents.show', [$registration, 'cor']))
                ->assertOk()
                ->assertHeader('content-disposition', 'inline; filename=certificate-of-registration.pdf');
        }
    }

    public function test_registration_review_workspace_has_responsive_dark_mode_styles(): void
    {
        $styles = file_get_contents(public_path('css/registration-review.css'));

        $this->assertStringContainsString('html[data-theme=dark] .registration-status-card.selected', $styles);
        $this->assertStringContainsString('html[data-theme=dark] .registration-decision-card textarea', $styles);
        $this->assertStringContainsString('.registration-review-table td:not(:first-child)::before', $styles);
    }

    public function test_both_documents_must_be_verified_for_the_registration_to_be_document_verified(): void
    {
        Storage::fake('local');
        Queue::fake();
        $registration = $this->registration();
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('nstp_admin.registrations.review', $registration), [
                'cor_review_status' => 'verified',
                'formal_photo_review_status' => 'verified',
                'review_notes' => 'The submitted details match both readable files.',
            ])
            ->assertRedirect(route('nstp_admin.registrations.show', $registration))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('temporary_password');

        $registration->refresh();
        $this->assertSame('verified', $registration->status);
        $this->assertSame('verified', $registration->cor_review_status);
        $this->assertSame($admin->id, $registration->reviewed_by);
        $this->assertNotNull($registration->reviewed_at);

        $student = User::where('email', $registration->email)->firstOrFail();
        $this->assertSame('Juan Santos Dela Cruz', $student->name);
        $this->assertSame('student', $student->role);
        $this->assertSame('active', $student->status);
        $this->assertTrue($student->must_change_password);
        $this->assertFalse($student->must_upload_student_documents);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'student_registration_id' => $registration->id,
            'student_number' => $registration->student_number,
        ]);
        $this->assertDatabaseHas('student_registrations', ['id' => $registration->id, 'status' => 'verified']);
        Queue::assertNotPushed(SendAccountCredentials::class);

        $this->actingAs($admin)->get(route('nstp_admin.students.index'))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee($student->email);

        $this->actingAs($admin)
            ->patch(route('nstp_admin.registrations.review', $registration), [
                'cor_review_status' => 'verified',
                'formal_photo_review_status' => 'verified',
                'review_notes' => 'Rechecked.',
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('student_profiles', 1);
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

    public function test_nstp_admin_can_archive_and_restore_a_registration(): void
    {
        Storage::fake('local');
        $registration = $this->registration();
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('nstp_admin.registrations.archive', $registration))
            ->assertRedirect(route('nstp_admin.registrations.index'));

        $registration->refresh();
        $this->assertNotNull($registration->archived_at);
        $this->assertSame($admin->id, $registration->archived_by);
        $this->actingAs($admin)->get(route('nstp_admin.registrations.index'))
            ->assertViewHas('registrations', fn ($registrations) => $registrations->total() === 0);
        $this->actingAs($admin)->get(route('nstp_admin.registrations.index', ['record_state' => 'archived']))
            ->assertViewHas('registrations', fn ($registrations) => $registrations->contains('id', $registration->id));

        $this->actingAs($admin)
            ->patch(route('nstp_admin.registrations.restore', $registration))
            ->assertRedirect(route('nstp_admin.registrations.show', $registration));

        $this->assertNull($registration->fresh()->archived_at);
    }

    public function test_only_super_admin_can_permanently_delete_an_archived_registration(): void
    {
        Storage::fake('local');
        Queue::fake();
        $registration = $this->registration();
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->patch(route('nstp_admin.registrations.review', $registration), [
            'cor_review_status' => 'verified',
            'formal_photo_review_status' => 'verified',
        ])->assertSessionHasNoErrors();

        $student = User::where('email', $registration->email)->firstOrFail();
        $corPath = $registration->cor_path;
        $photoPath = $registration->formal_photo_path;

        $this->actingAs($nstpAdmin)->patch(route('nstp_admin.registrations.archive', $registration));
        $this->actingAs($nstpAdmin)->delete(route('nstp_admin.registrations.destroy', $registration), [
            'confirmation' => $registration->reference_code,
        ])->assertForbidden();

        $this->actingAs($superAdmin)->delete(route('admin.registrations.destroy', $registration), [
            'confirmation' => $registration->reference_code,
        ])->assertRedirect(route('admin.registrations.index', ['record_state' => 'archived']));

        $this->assertDatabaseMissing('student_registrations', ['id' => $registration->id]);
        $this->assertDatabaseHas('users', ['id' => $student->id, 'role' => 'student']);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'student_registration_id' => null,
        ]);
        Storage::disk('local')->assertMissing($corPath);
        Storage::disk('local')->assertMissing($photoPath);
    }

    public function test_active_registration_cannot_be_permanently_deleted(): void
    {
        Storage::fake('local');
        $registration = $this->registration();
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($superAdmin)->delete(route('admin.registrations.destroy', $registration), [
            'confirmation' => $registration->reference_code,
        ])->assertStatus(409);

        $this->assertDatabaseHas('student_registrations', ['id' => $registration->id]);
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
            'province_code' => '036900000',
            'city_municipality' => 'Tarlac City',
            'city_municipality_code' => '036916000',
            'barangay' => 'San Vicente',
            'barangay_code' => '036916076',
            'date_of_birth' => '2007-04-15',
            'birth_province' => 'Tarlac',
            'birth_province_code' => '036900000',
            'birth_city_municipality' => 'Tarlac City',
            'birth_city_municipality_code' => '036916000',
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
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'nstp_level' => 'nstp_1',
        ]);
    }
}
