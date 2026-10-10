<?php

namespace Tests\Feature;

use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_operational_workflows_are_seeded_and_visible_to_authorized_administrators(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);

        $this->assertEqualsCanonicalizing([
            'registration_review',
            'document_verification',
            'account_creation',
            'component_selection',
            'rotc_approval',
            'sectioning',
            'grading',
            'archiving',
        ], WorkflowDefinition::pluck('key')->all());

        $this->actingAs($superAdmin)->get(route('admin.workflows.index'))
            ->assertOk()
            ->assertSee('Workflow Rules Builder')
            ->assertSee('Registration Review')
            ->assertSee('Document Verification')
            ->assertSee('Account Creation')
            ->assertSee('Component Selection')
            ->assertSee('ROTC Approval')
            ->assertSee('Sectioning')
            ->assertSee('Grading')
            ->assertSee('Archiving');

        $this->actingAs($nstpAdmin)->get(route('nstp_admin.workflows.index'))
            ->assertOk()
            ->assertSee('Workflow Rules Builder');
        $this->actingAs($facilitator)->get('/admin/workflows')->assertForbidden();
        $this->actingAs($facilitator)->get('/nstp-admin/workflows')->assertForbidden();
    }

    public function test_admin_can_reorder_and_change_workflow_execution_rules(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $workflow = WorkflowDefinition::where('key', 'registration_review')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.workflows.update', $workflow), [
            'name' => 'Institution Registration Review',
            'description' => 'Institution-specific review sequence.',
            'is_active' => '1',
            'steps' => [
                'validate_files' => ['enabled' => '0', 'mode' => 'manual', 'sort_order' => 30],
                'require_correction_notes' => ['enabled' => '1', 'mode' => 'manual', 'sort_order' => 10],
                'automatic_status_transition' => ['enabled' => '1', 'mode' => 'manual', 'sort_order' => 20],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $workflow->refresh();
        $this->assertSame('Institution Registration Review', $workflow->name);
        $this->assertSame($admin->id, $workflow->updated_by);
        $this->assertSame([
            'require_correction_notes',
            'automatic_status_transition',
            'validate_files',
        ], collect($workflow->resolvedSteps())->pluck('key')->all());
        $this->assertFalse(WorkflowDefinition::ruleEnabled('registration_review', 'validate_files'));
        $this->assertFalse(WorkflowDefinition::runsAutomatically('registration_review', 'automatic_status_transition'));
    }

    public function test_inactive_workflow_disables_its_rules_while_other_workflows_keep_their_defaults(): void
    {
        WorkflowDefinition::where('key', 'archiving')->update(['is_active' => false]);

        $this->assertFalse(WorkflowDefinition::ruleEnabled('archiving', 'allow_restore'));
        $this->assertFalse(WorkflowDefinition::ruleEnabled('archiving', 'require_delete_confirmation'));
        $this->assertTrue(WorkflowDefinition::ruleEnabled('grading', 'enforce_weight_total'));
        $this->assertTrue(WorkflowDefinition::runsAutomatically('sectioning', 'create_sections_when_full'));
    }

    public function test_component_workflow_can_allow_updates_outside_the_selection_window(): void
    {
        $workflow = WorkflowDefinition::where('key', 'component_selection')->firstOrFail();
        $workflow->update(['steps' => collect($workflow->resolvedSteps())->map(fn (array $step): array => [
            'key' => $step['key'],
            'enabled' => false,
            'mode' => $step['mode'],
            'sort_order' => $step['sort_order'],
        ])->all()]);
        SystemSetting::updateOrCreate(['key' => 'component_selection_open'], ['value' => '0']);

        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $cwts = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $lts = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $startYear = now()->month >= 6 ? now()->year : now()->year - 1;
        $academicYear = $startYear.'-'.($startYear + 1);
        $semester = now()->month >= 6 ? 'first' : 'second';
        $enrollment = NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $cwts->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
            'shirt_size' => 'M',
            'status' => 'enrolled',
        ]);

        $this->actingAs($student)->get(route('student.component.edit'))
            ->assertOk()
            ->assertSee('Update enrollment details')
            ->assertSee('Literacy Training Service');

        $this->actingAs($student)->put(route('student.component.update'), [
            'nstp_component_id' => $lts->id,
            'shirt_size' => 'L',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($lts->id, $enrollment->fresh()->component_id);
        $this->assertSame('L', $enrollment->fresh()->shirt_size);
    }
}
