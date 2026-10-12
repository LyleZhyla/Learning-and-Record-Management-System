<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\StudentRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastNameOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_directories_are_ordered_by_last_name(): void
    {
        $superAdmin = User::factory()->create(['name' => 'Root Administrator', 'role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['name' => 'NSTP Administrator', 'role' => 'nstp_admin', 'status' => 'active']);
        User::factory()->create(['name' => 'Aaron Zulu', 'role' => 'facilitator', 'status' => 'active']);
        User::factory()->create(['name' => 'Zoe Alpha', 'role' => 'facilitator', 'status' => 'active']);
        User::factory()->create(['name' => 'Ben Young', 'role' => 'student', 'status' => 'active']);
        User::factory()->create(['name' => 'Yna Bravo', 'role' => 'student', 'status' => 'active']);

        $this->actingAs($superAdmin)->get('/admin/users?role=facilitator')
            ->assertOk()
            ->assertSeeInOrder(['Zoe Alpha', 'Aaron Zulu']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/accounts')
            ->assertOk()
            ->assertSeeInOrder(['Zoe Alpha', 'Aaron Zulu']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/students')
            ->assertOk()
            ->assertSeeInOrder(['Yna Bravo', 'Ben Young']);
    }

    public function test_last_name_sort_key_ignores_common_name_suffixes(): void
    {
        $user = User::factory()->create(['name' => 'Juan Dela Cruz Jr.']);

        $this->assertSame('cruz', $user->last_name_sort_key);
    }

    public function test_student_directory_displays_last_name_first_and_uses_registration_surname(): void
    {
        $admin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create([
            'name' => 'Juan Santos Dela Cruz',
            'email' => 'juan.delacruz@example.test',
            'role' => 'student',
            'status' => 'active',
        ]);
        StudentRegistration::create([
            'reference_code' => 'NSTP-SORT-0001',
            'status' => 'pending',
            'cor_path' => 'student-registrations/cor/test.pdf',
            'formal_photo_path' => 'student-registrations/formal-photos/test.jpg',
            'email' => $student->email,
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
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
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_relationship' => 'Mother',
            'emergency_contact_number' => '09987654321',
            'emergency_same_address' => true,
            'student_number' => '2026123456',
            'college' => 'College of Education',
            'course' => 'Bachelor of Secondary Education',
            'year_section' => '1A',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'nstp_level' => 'nstp_1',
        ]);

        $this->assertSame('dela cruz', $student->fresh()->last_name_sort_key);
        $this->actingAs($admin)->get('/nstp-admin/students')
            ->assertOk()
            ->assertSee('Dela Cruz, Juan S.');
    }
}
