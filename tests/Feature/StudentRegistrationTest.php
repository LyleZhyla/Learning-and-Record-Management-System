<?php

namespace Tests\Feature;

use App\Models\StudentRegistration;
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
            'course' => 'Bachelor of Secondary Education',
            'major' => 'English',
            'year_section' => '1-A',
            'privacy_consent' => '1',
        ], $overrides);
    }
}
