<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentSelectionAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_nstp_admin_and_super_admin_share_control_of_student_component_selection(): void
    {
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($nstpAdmin)->get('/nstp-admin/components')
            ->assertOk()
            ->assertSee('Component selection is open')
            ->assertSee('Close selection');

        $this->actingAs($nstpAdmin)->patch('/nstp-admin/components/selection-availability', [
            'is_open' => '0',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(SystemSetting::componentSelectionIsOpen());
        $this->assertDatabaseHas('system_settings', [
            'key' => 'component_selection_open',
            'value' => '0',
            'updated_by' => $nstpAdmin->id,
        ]);

        $this->actingAs($superAdmin)->get('/admin/components')
            ->assertOk()
            ->assertSee('Component selection is closed')
            ->assertSee('Open selection')
            ->assertSee($nstpAdmin->name);

        $this->actingAs($superAdmin)->patch('/admin/components/selection-availability', [
            'is_open' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(SystemSetting::componentSelectionIsOpen());
        $this->assertDatabaseHas('system_settings', [
            'key' => 'component_selection_open',
            'value' => '1',
            'updated_by' => $superAdmin->id,
        ]);
    }

    public function test_closed_selection_blocks_new_student_submissions_but_keeps_existing_selections_visible(): void
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $newStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $enrolledStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        [$academicYear, $semester] = $this->currentTerm();
        NstpEnrollment::create([
            'student_id' => $enrolledStudent->id,
            'component_id' => $component->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'shirt_size' => 'M',
            'status' => 'enrolled',
        ]);
        SystemSetting::where('key', 'component_selection_open')->update(['value' => '0']);

        $this->actingAs($newStudent)->get('/student/component')
            ->assertOk()
            ->assertSee('NSTP selection is closed')
            ->assertSee('Selection is not accepting submissions')
            ->assertDontSee('Save enrollment preferences');

        $this->actingAs($newStudent)->put('/student/component', [
            'nstp_component_id' => $component->id,
            'shirt_size' => 'L',
        ])->assertRedirect()->assertSessionHasErrors('selection');

        $this->assertDatabaseMissing('nstp_enrollments', ['student_id' => $newStudent->id]);

        $this->actingAs($enrolledStudent)->get('/student/component')
            ->assertOk()
            ->assertSee('Your NSTP selection')
            ->assertSee('CWTS')
            ->assertSee('Final selection');
    }

    /** @return array{string, string} */
    private function currentTerm(): array
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return [$start.'-'.($start + 1), now()->month >= 6 ? 'first' : 'second'];
    }
}
