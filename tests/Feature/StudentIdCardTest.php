<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentIdCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_id_is_generated_from_profile_enrollment_and_permanent_qr(): void
    {
        $student = User::factory()->create([
            'name' => 'Juan Santos Dela Cruz',
            'email' => 'juan@example.test',
            'role' => 'student',
            'status' => 'active',
        ]);
        StudentProfile::create([
            'user_id' => $student->id,
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
        ]);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);

        $this->actingAs($student)->get('/student/student-id')
            ->assertOk()
            ->assertSee('Juan S. Dela Cruz')
            ->assertSee('Bachelor of Secondary Education (BSEd)')
            ->assertSee('CWTS')
            ->assertSee('Maria Dela Cruz')
            ->assertSee('09987654321')
            ->assertSee('2026123456')
            ->assertSee('SCAN FOR ATTENDANCE')
            ->assertSee('<svg', false)
            ->assertSee('Print / Save as PDF');
    }

    public function test_student_id_page_is_restricted_to_student_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->get('/student/student-id')->assertRedirect('/login');
        $this->actingAs($admin)->get('/student/student-id')->assertForbidden();
    }

    public function test_student_id_still_renders_with_an_incomplete_profile(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/student/student-id')
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('Some ID information is incomplete.')
            ->assertSee('Not provided')
            ->assertSee('<svg', false);
    }
}
