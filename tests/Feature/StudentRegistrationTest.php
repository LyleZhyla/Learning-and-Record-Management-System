<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_a_complete_registration(): void
    {
        Storage::fake('local');

        $response = $this->post('/register', $this->validPayload());

        $response->assertRedirect('/register')->assertSessionHasNoErrors()->assertSessionHas('reference_code');
        $registration = StudentRegistration::firstOrFail();

        $this->assertSame('pending', $registration->status);
        $this->assertSame('Juan Dela Cruz', $registration->first_name.' '.$registration->last_name);
        $this->assertSame('1A', $registration->year_section);
        $this->assertTrue($registration->emergency_same_address);
        $this->assertNull($registration->emergency_address);
        Storage::disk('local')->assertExists($registration->cor_path);
        Storage::disk('local')->assertExists($registration->formal_photo_path);
    }

    public function test_contact_and_student_number_formats_are_enforced(): void
    {
        $payload = $this->validPayload([
            'contact_number' => '9123456789',
            'emergency_contact_number' => '08123456789',
            'student_number' => '1912345678',
        ]);

        $this->post('/register', $payload)->assertSessionHasErrors([
            'contact_number',
            'emergency_contact_number',
            'student_number',
        ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_cor_and_formal_photo_are_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['cor'], $payload['formal_photo']);

        $this->post('/register', $payload)->assertSessionHasErrors(['cor', 'formal_photo']);
    }

    public function test_course_and_major_must_belong_to_the_selected_college(): void
    {
        $payload = $this->validPayload([
            'college' => 'College of Veterinary Medicine',
            'course' => 'Bachelor of Secondary Education (BSEd)',
            'major' => 'Marketing Management',
        ]);

        $this->post('/register', $payload)->assertSessionHasErrors(['course']);
        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_registration_wizard_keeps_a_tab_scoped_refresh_draft(): void
    {
        $script = file_get_contents(public_path('js/student-registration.js'));

        $this->assertStringContainsString("const draftKey = 'smartNstpRegistrationDraft'", $script);
        $this->assertStringContainsString('sessionStorage.setItem', $script);
        $this->assertStringContainsString('showStep(restoredStep)', $script);
        $this->get('/register')->assertOk();
    }

    public function test_other_year_and_section_requires_a_custom_value(): void
    {
        $payload = $this->validPayload([
            'year_section_selection' => 'Others',
            'year_section_other' => '',
        ]);

        $this->post('/register', $payload)->assertSessionHasErrors('year_section_other');
    }

    public function test_duplicate_email_is_rejected_case_insensitively_and_returns_to_personal_information(): void
    {
        Storage::fake('local');
        $this->post('/register', $this->validPayload())->assertSessionHasNoErrors();

        $this->post('/register', $this->validPayload([
            'email' => '  JUAN.DELACRUZ@EXAMPLE.TEST  ',
            'student_number' => '2026123457',
        ]))->assertSessionHasErrors('email');

        $this->assertDatabaseCount('student_registrations', 1);
        $this->assertCount(2, Storage::disk('local')->allFiles('student-registrations'));
        $this->get('/register')
            ->assertOk()
            ->assertSee('data-server-error-step="1"', false)
            ->assertSee('An account or registration already uses this email address.');
    }

    public function test_duplicate_student_number_is_rejected_and_returns_to_academic_information(): void
    {
        Storage::fake('local');
        $this->post('/register', $this->validPayload())->assertSessionHasNoErrors();

        $this->post('/register', $this->validPayload([
            'email' => 'another.student@example.test',
            'student_number' => '2026123456',
        ]))->assertSessionHasErrors('student_number');

        $this->assertDatabaseCount('student_registrations', 1);
        $this->get('/register')
            ->assertOk()
            ->assertSee('data-server-error-step="3"', false)
            ->assertSee('A student is already registered with this student number.');
    }

    public function test_existing_account_email_and_student_profile_number_cannot_be_registered_again(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'email' => 'existing.student@example.test',
        ]);
        $this->createStudentProfile($student, '2026987654');

        $this->post('/register', $this->validPayload([
            'email' => 'EXISTING.STUDENT@EXAMPLE.TEST',
            'student_number' => '2026987654',
        ]))->assertSessionHasErrors(['email', 'student_number']);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'cor' => UploadedFile::fake()->create('certificate-of-registration.pdf', 350, 'application/pdf'),
            'formal_photo' => UploadedFile::fake()->image('formal-photo.jpg', 600, 800),
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'extension_name_na' => '1',
            'middle_name' => 'Santos',
            'province' => 'Bulacan',
            'province_code' => '031400000',
            'city_municipality' => 'Malolos City',
            'city_municipality_code' => '031410000',
            'barangay' => 'Santo Niño',
            'barangay_code' => '031410018',
            'date_of_birth' => '2007-04-15',
            'birth_province' => 'Bulacan',
            'birth_province_code' => '031400000',
            'birth_city_municipality' => 'Malolos City',
            'birth_city_municipality_code' => '031410000',
            'religion_selection' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09123456789',
            'email' => 'juan.delacruz@example.test',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09987654321',
            'emergency_same_address' => '1',
            'student_number' => '2026123456',
            'college' => 'College of Education',
            'course' => 'Bachelor of Secondary Education (BSEd)',
            'major' => 'Mathematics',
            'year_section_selection' => '1A',
            'privacy_consent' => '1',
        ], $overrides);
    }

    private function createStudentProfile(User $student, string $studentNumber): StudentProfile
    {
        return StudentProfile::create([
            'user_id' => $student->id,
            'last_name' => 'Existing',
            'first_name' => 'Student',
            'province' => 'Bulacan',
            'province_code' => '031400000',
            'city_municipality' => 'Malolos City',
            'city_municipality_code' => '031410000',
            'barangay' => 'Santo Niño',
            'barangay_code' => '031410018',
            'date_of_birth' => '2007-04-15',
            'birth_province' => 'Bulacan',
            'birth_province_code' => '031400000',
            'birth_city_municipality' => 'Malolos City',
            'birth_city_municipality_code' => '031410000',
            'religion' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09123456789',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_relationship' => 'Guardian',
            'emergency_contact_number' => '09987654321',
            'emergency_same_address' => true,
            'student_number' => $studentNumber,
            'college' => 'College of Education',
            'course' => 'Bachelor of Secondary Education (BSEd)',
            'major' => 'Mathematics',
            'year_section' => '1A',
        ]);
    }
}
