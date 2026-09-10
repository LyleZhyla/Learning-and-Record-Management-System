<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportedStudentDocumentRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_imported_student_is_sent_to_required_documents_after_signing_in(): void
    {
        $student = $this->importedStudent();

        $this->post('/login', [
            'email' => $student->email,
            'password' => 'Generated!Pass2026',
        ])->assertRedirect('/student/required-documents');

        $this->assertAuthenticatedAs($student);
        $this->get('/student/dashboard')->assertRedirect('/student/required-documents');
        $this->get('/student/required-documents')
            ->assertOk()
            ->assertSee('Upload your student documents')
            ->assertSee('Certificate of Registration')
            ->assertSee('Formal photo');
    }

    public function test_both_cor_and_formal_photo_are_required_before_portal_access(): void
    {
        $student = $this->importedStudent();

        $this->actingAs($student)->post('/student/required-documents', [])
            ->assertSessionHasErrors(['cor', 'formal_photo']);

        $this->assertTrue($student->fresh()->must_upload_student_documents);
        $this->actingAs($student)->get('/student/dashboard')
            ->assertRedirect('/student/required-documents');
    }

    public function test_uploading_both_documents_unlocks_the_student_portal(): void
    {
        Storage::fake('local');
        $student = $this->importedStudent();

        $this->actingAs($student)->post('/student/required-documents', [
            'cor' => UploadedFile::fake()->create('current-cor.pdf', 500, 'application/pdf'),
            'formal_photo' => UploadedFile::fake()->image('formal-photo.jpg', 600, 800),
        ])->assertSessionHasNoErrors()
            ->assertRedirect('/student/dashboard');

        $student->refresh();
        $student->studentProfile->refresh();
        $this->assertFalse($student->must_upload_student_documents);
        $this->assertNotNull($student->studentProfile->cor_path);
        $this->assertNotNull($student->studentProfile->formal_photo_path);
        Storage::disk('local')->assertExists($student->studentProfile->cor_path);
        Storage::disk('local')->assertExists($student->studentProfile->formal_photo_path);
        $this->actingAs($student)->get('/student/dashboard')->assertOk();
    }

    private function importedStudent(): User
    {
        $student = User::factory()->create([
            'name' => 'Imported Student',
            'email' => 'imported.student@example.test',
            'password' => Hash::make('Generated!Pass2026'),
            'role' => 'student',
            'status' => 'active',
            'must_change_password' => true,
            'must_upload_student_documents' => true,
        ]);

        $student->studentProfile()->create([
            'last_name' => 'Student',
            'first_name' => 'Imported',
            'province' => 'Pangasinan',
            'province_code' => '015500000',
            'city_municipality' => 'Lingayen',
            'city_municipality_code' => '015522000',
            'barangay' => 'Poblacion',
            'barangay_code' => '015522001',
            'date_of_birth' => '2006-05-20',
            'birth_province' => 'Pangasinan',
            'birth_province_code' => '015500000',
            'birth_city_municipality' => 'Lingayen',
            'birth_city_municipality_code' => '015522000',
            'religion' => 'Roman Catholic',
            'sex' => 'Male',
            'blood_type' => 'O+',
            'contact_number' => '09171234567',
            'emergency_contact_name' => 'Maria Student',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09981234567',
            'emergency_same_address' => true,
            'student_number' => '2026000010',
            'college' => 'College of Engineering and Technology',
            'course' => 'Bachelor of Science in Information Technology',
            'major' => 'N/A',
            'year_section' => '1A',
        ]);

        return $student;
    }
}
