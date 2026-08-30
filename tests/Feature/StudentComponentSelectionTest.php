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

    public function test_student_has_a_dedicated_page_for_component_and_shirt_size_selection(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        [$academicYear, $semester] = $this->currentTerm();

        $this->actingAs($student)->get('/student/component')
            ->assertOk()
            ->assertSee('NSTP Selection')
            ->assertSee('Choose your NSTP component')
            ->assertSee('ROTC category')
            ->assertSee('Shirt size')
            ->assertSee('Save enrollment preferences')
            ->assertSee('CWTS');

        $this->actingAs($student)->get('/student/profile')
            ->assertOk()
            ->assertDontSee('Choose your component');

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $component->id,
            'shirt_size' => 'M',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'student_id' => $student->id,
            'component_id' => $component->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'shirt_size' => 'M',
            'status' => 'enrolled',
        ]);
    }

    public function test_component_and_valid_shirt_size_are_both_required(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $component->id,
        ])->assertSessionHasErrors('shirt_size');

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $component->id,
            'shirt_size' => 'INVALID',
        ])->assertSessionHasErrors('shirt_size');
    }

    public function test_rotc_category_is_required_only_when_rotc_is_selected(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $rotc->id,
            'shirt_size' => 'M',
        ])->assertSessionHasErrors('rotc_category');

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $rotc->id,
            'shirt_size' => 'M',
            'rotc_category' => 'MS-31',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'student_id' => $student->id,
            'component_id' => $rotc->id,
            'shirt_size' => 'M',
            'rotc_category' => 'MS-31',
        ]);

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $cwts->id,
            'shirt_size' => 'L',
            'rotc_category' => 'MS-41',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'student_id' => $student->id,
            'component_id' => $cwts->id,
            'shirt_size' => 'L',
            'rotc_category' => null,
        ]);
    }

    public function test_changing_component_clears_section_and_updates_shirt_size(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $rotc = NstpComponent::create(['code' => 'ROTC', 'name' => 'Reserve Officers Training Corps', 'default_section_capacity' => 40, 'is_active' => true]);
        [$academicYear, $semester] = $this->currentTerm();
        $section = NstpSection::create(['component_id' => $cwts->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => $academicYear, 'semester' => $semester, 'capacity' => 40, 'status' => 'active']);
        $enrollment = NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $cwts->id, 'section_id' => $section->id, 'academic_year' => $academicYear, 'semester' => $semester, 'shirt_size' => 'S', 'status' => 'enrolled']);

        $this->actingAs($student)->put('/student/component', [
            'nstp_component_id' => $rotc->id,
            'shirt_size' => 'L',
            'rotc_category' => 'MS-41',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('nstp_enrollments', [
            'id' => $enrollment->id,
            'component_id' => $rotc->id,
            'shirt_size' => 'L',
            'rotc_category' => 'MS-41',
            'section_id' => null,
        ]);
    }

    public function test_inactive_components_and_non_students_cannot_use_student_selection(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $inactive = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'default_section_capacity' => 40, 'is_active' => false]);

        $this->actingAs($student)->put('/student/component', ['nstp_component_id' => $inactive->id, 'shirt_size' => 'M'])
            ->assertSessionHasErrors('nstp_component_id');

        $this->actingAs($coordinator)->get('/student/component')->assertForbidden();
        $this->actingAs($coordinator)->put('/student/component', ['nstp_component_id' => $inactive->id, 'shirt_size' => 'M'])
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
