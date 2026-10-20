<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentProfileCompletionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_without_a_profile_is_redirected_from_the_portal_to_the_profile_editor(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'must_complete_student_profile' => true,
        ]);

        $this->actingAs($student)
            ->get('/student/dashboard')
            ->assertRedirect('/student/profile?edit=1')
            ->assertSessionHas('warning');

        $this->assertTrue($student->fresh()->must_complete_student_profile);

        $this->get('/student/profile')
            ->assertOk()
            ->assertSee('Complete your profile to continue.')
            ->assertSee('student-profile-form', false)
            ->assertDontSee('Cancel editing');
    }

    public function test_student_with_a_partially_completed_profile_remains_locked(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'must_complete_student_profile' => true,
        ]);
        $student->studentProfile()->create($this->completeProfile(['major' => null]));

        $this->assertFalse($student->fresh()->hasCompleteStudentProfile());

        $this->actingAs($student)
            ->get('/student/materials')
            ->assertRedirect('/student/profile?edit=1');
    }

    public function test_complete_profile_unlocks_student_routes(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'must_complete_student_profile' => true,
        ]);
        $student->studentProfile()->create($this->completeProfile());

        $this->assertTrue($student->fresh()->hasCompleteStudentProfile());
        $this->assertFalse($student->fresh()->must_complete_student_profile);

        $this->actingAs($student)
            ->get('/student/dashboard')
            ->assertOk();
    }

    public function test_profile_and_supporting_routes_remain_available_while_locked(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'must_complete_student_profile' => true,
        ]);

        $this->actingAs($student)->get('/student/profile')->assertOk();
        $this->get('/locations/provinces/140100000/cities')->assertOk();
        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    private function completeProfile(array $overrides = []): array
    {
        return array_replace([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
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
        ], $overrides);
    }
}
