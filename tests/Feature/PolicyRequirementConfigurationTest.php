<?php

namespace Tests\Feature;

use App\Models\GradingSetting;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyRequirementConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrators_can_open_the_policy_configuration(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($superAdmin)->get(route('admin.policies.index'))
            ->assertOk()
            ->assertSee('Policies &amp; Requirements', false)
            ->assertSee('Registration opening')
            ->assertSee('Required documents');

        $this->actingAs($nstpAdmin)->get(route('nstp_admin.policies.index'))
            ->assertOk()
            ->assertSee('Passing grade');

        $this->actingAs($student)->get('/admin/policies')->assertForbidden();
        $this->actingAs($student)->get('/nstp-admin/policies')->assertForbidden();
    }

    public function test_admin_can_update_all_policy_areas_and_existing_grading_settings(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $component = NstpComponent::create([
            'code' => 'CWTS',
            'name' => 'Civic Welfare Training Service',
            'default_section_capacity' => 40,
            'is_active' => true,
        ]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'code' => 'CWTS-01',
            'name' => 'CWTS Section 1',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        GradingSetting::create([
            'section_id' => $section->id,
            'passing_percentage' => 75,
            'highest_grade' => 1,
            'passing_grade' => 3,
            'failing_grade' => 5,
        ]);

        $this->actingAs($admin)->put(route('admin.policies.update'), [
            'student_registration_open' => '0',
            'student_registration_academic_year' => '2028-2029',
            'student_registration_semester' => 'second',
            'component_selection_open' => '0',
            'inactivity_timeout_minutes' => 20,
            'default_passing_percentage' => 80,
            'default_passing_grade' => 2.75,
            'required_documents_enforced' => '0',
            'apply_grading_policy_to_existing_sections' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse(SystemSetting::studentRegistrationIsOpen());
        $this->assertSame('2028-2029', SystemSetting::studentRegistrationAcademicYear());
        $this->assertSame('second', SystemSetting::studentRegistrationSemester());
        $this->assertFalse(SystemSetting::componentSelectionIsOpen());
        $this->assertSame(20, SystemSetting::inactivityTimeoutMinutes());
        $this->assertSame(80.0, SystemSetting::defaultPassingPercentage());
        $this->assertSame(2.75, SystemSetting::defaultPassingGrade());
        $this->assertFalse(SystemSetting::requiredDocumentsAreEnforced());

        $gradingSetting = GradingSetting::firstOrFail();
        $this->assertSame('80.00', $gradingSetting->passing_percentage);
        $this->assertSame('2.75', $gradingSetting->passing_grade);
        $this->assertSame(80.0, app(GradeService::class)->defaultSettings()['passing_percentage']);
        $this->assertSame(2.75, app(GradeService::class)->defaultSettings()['passing_grade']);
    }

    public function test_advisory_document_policy_allows_an_imported_student_into_the_portal(): void
    {
        SystemSetting::updateOrCreate(['key' => 'required_documents_enforced'], ['value' => '0']);
        $student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'must_upload_student_documents' => true,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
    }
}
