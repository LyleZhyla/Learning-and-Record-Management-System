<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentComponentSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_choose_an_active_component_from_their_profile(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        [$academicYear, $semester] = $this->currentTerm();

        $this->actingAs($student)->get('/student/profile')
            ->assertOk()
            ->assertSee('Choose your component')
            ->assertSee('Save component selection')
            ->assertSee('CWTS');

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $component->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'student_id' => $student->id,
            'component_id' => $component->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'status' => 'enrolled',
        ]);
    }

    public function test_changing_component_clears_the_students_previous_section(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        [$academicYear, $semester] = $this->currentTerm();
        $section = NstpSection::create(['component_id' => $cwts->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => $academicYear, 'semester' => $semester, 'capacity' => 40, 'status' => 'active']);
        $enrollment = NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $cwts->id, 'section_id' => $section->id, 'academic_year' => $academicYear, 'semester' => $semester, 'status' => 'enrolled']);

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $rotc->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'id' => $enrollment->id,
            'component_id' => $rotc->id,
            'section_id' => null,
        ]);
    }

    public function test_inactive_components_and_non_students_cannot_use_student_component_selection(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $inactive = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'default_section_capacity' => 40, 'is_active' => false]);

        $this->actingAs($student)->put('/student/component', ['nstp_component_id' => $inactive->id])
            ->assertSessionHasErrors('nstp_component_id');

        $this->actingAs($coordinator)->put('/student/component', ['nstp_component_id' => $inactive->id])
            ->assertForbidden();
    }

    /** @return array{string, string} */
    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
