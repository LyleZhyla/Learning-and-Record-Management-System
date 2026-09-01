<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\StudentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_open_guided_profile_editor_with_registration_features(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/student/profile')
            ->assertOk()
            ->assertSee('Personal Information')
            ->assertSee('Emergency Contact')
            ->assertSee('Academic Information')
            ->assertSee('student-profile.js')
            ->assertSee('Select province')
            ->assertSee('Select college');
    }

    public function test_student_can_save_only_their_own_validated_details(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->put('/student/profile/details', $this->validPayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $profile = StudentProfile::whereBelongsTo($student)->firstOrFail();
        $this->assertSame('2026123456', $profile->student_number);
        $this->assertSame('Mathematics', $profile->major);
        $this->assertSame('Juan Santos Dela Cruz', $student->fresh()->name);
        $this->assertNull($other->studentProfile);
    }

    public function test_student_profile_rejects_typing_and_academic_mistakes(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $payload = $this->validPayload([
            'contact_number' => '9123456789',
            'student_number' => '1912345678',
            'college' => 'College of Veterinary Medicine',
            'course' => 'Bachelor of Secondary Education (BSEd)',
            'major' => 'Marketing Management',
        ]);

        $this->actingAs($student)->put('/student/profile/details', $payload)
            ->assertSessionHasErrors(['contact_number', 'student_number', 'course']);

        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_existing_registration_details_are_prefilled_and_linked(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'email' => 'juan@example.test']);
        $registration = StudentRegistration::create([
            ...$this->registrationData(),
            'email' => $student->email,
        ]);

        $this->actingAs($student)->get('/student/profile')
            ->assertOk()
            ->assertSee('2026123456')
            ->assertSee('Juan');

        $this->actingAs($student)->put('/student/profile/details', $this->validPayload(['email' => $student->email]))
            ->assertSessionHasNoErrors();

        $this->assertSame($registration->id, $student->fresh()->studentProfile->student_registration_id);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'extension_name_na' => '1',
            'middle_name' => 'Santos',
            'province' => 'Abra',
            'province_code' => '140100000',
            'city_municipality' => 'Bangued',
            'city_municipality_code' => '140101000',
            'barangay' => 'Agtangao',
            'barangay_code' => '140101001',
            'date_of_birth' => '2007-04-15',
            'birth_province' => 'Abra',
            'birth_province_code' => '140100000',
            'birth_city_municipality' => 'Bangued',
            'birth_city_municipality_code' => '140101000',
            'religion_selection' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09123456789',
            'email' => 'updated.student@example.test',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09987654321',
            'emergency_same_address' => '1',
            'student_number' => '2026123456',
            'college' => 'College of Education',
            'course' => 'Bachelor of Secondary Education (BSEd)',
            'major' => 'Mathematics',
            'year_section_selection' => '1A',
        ], $overrides);
    }

    private function registrationData(): array
    {
        return [
            'reference_code' => 'NSTP-2026-PROFILE1',
            'status' => 'approved',
            'cor_path' => 'student-registrations/cor/sample.pdf',
            'formal_photo_path' => 'student-registrations/formal-photos/sample.jpg',
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'province' => 'Abra',
            'province_code' => '140100000',
            'city_municipality' => 'Bangued',
            'city_municipality_code' => '140101000',
            'barangay' => 'Agtangao',
            'barangay_code' => '140101001',
            'date_of_birth' => '2007-04-15',
            'birth_province' => 'Abra',
            'birth_province_code' => '140100000',
            'birth_city_municipality' => 'Bangued',
            'birth_city_municipality_code' => '140101000',
            'religion' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09123456789',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09987654321',
            'emergency_same_address' => true,
            'student_number' => '2026123456',
            'college' => 'College of Education',
            'course' => 'Bachelor of Secondary Education (BSEd)',
            'major' => 'Mathematics',
            'year_section' => '1A',
        ];
    }
}
