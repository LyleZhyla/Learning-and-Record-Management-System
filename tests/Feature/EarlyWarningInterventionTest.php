<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\RiskThreshold;
use App\Models\StudentIntervention;
use App\Models\StudentNotification;
use App\Models\StudentRiskSnapshot;
use App\Models\User;
use App\Services\EarlyWarningService;
use App\Services\InstitutionContext;
use App\Services\InstitutionProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarlyWarningInterventionTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_breach_opens_an_assigned_case_and_alerts_student_facilitator_and_coordinator(): void
    {
        [$component, $coordinator, $facilitator, $student, $section] = $this->records();

        $snapshot = app(EarlyWarningService::class)->synchronize($student, $section, $this->riskSummary());

        $this->assertSame('at_risk', $snapshot?->status);
        $case = StudentIntervention::firstOrFail();
        $this->assertSame($facilitator->id, $case->assigned_to);
        $this->assertSame('attendance_counseling', $case->referral_type);
        $this->assertNotNull($case->follow_up_date);
        foreach ([$student, $facilitator, $coordinator] as $recipient) {
            $this->assertDatabaseHas('student_notifications', [
                'user_id' => $recipient->id, 'type' => StudentNotification::EARLY_WARNING, 'source_id' => $case->id,
            ]);
        }
        $studentAlert = StudentNotification::where('user_id', $student->id)->where('type', StudentNotification::EARLY_WARNING)->firstOrFail();
        $this->actingAs($student)->get("/notifications/events/{$studentAlert->id}/open")
            ->assertRedirect("/student/early-warning/{$case->id}");
        $this->actingAs($student)->get("/student/early-warning/{$case->id}")->assertOk()->assertSee('Risk score history');
    }

    public function test_coordinator_can_configure_only_their_component_thresholds(): void
    {
        [$component, $coordinator] = $this->records();
        $other = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $data = ['minimum_attendance_percentage' => 82, 'minimum_grade_percentage' => 78, 'maximum_missing_requirements' => 2, 'follow_up_days' => 5, 'escalation_days' => 10, 'minimum_improvement_points' => 8];

        $this->actingAs($coordinator)->put("/coordinator/early-warning/thresholds/{$component->id}", $data)->assertRedirect();
        $this->assertDatabaseHas('risk_thresholds', ['component_id' => $component->id, 'minimum_attendance_percentage' => 82, 'updated_by' => $coordinator->id]);
        $this->actingAs($coordinator)->put("/coordinator/early-warning/thresholds/{$other->id}", $data)->assertForbidden();
    }

    public function test_every_responsible_portal_can_open_its_scoped_intervention_workspace(): void
    {
        [, $coordinator, $facilitator, $student, $section] = $this->records();
        app(EarlyWarningService::class)->synchronize($student, $section, $this->riskSummary());
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);

        foreach ([
            [$admin, '/admin/early-warning'],
            [$nstpAdmin, '/nstp-admin/early-warning'],
            [$coordinator, '/coordinator/early-warning'],
            [$facilitator, '/facilitator/early-warning'],
            [$student, '/student/early-warning'],
        ] as [$user, $url]) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('Risk alerts and interventions');
        }
    }

    public function test_staff_can_record_action_plan_follow_up_assignment_and_resolution_history(): void
    {
        [, $coordinator, $facilitator, $student, $section] = $this->records();
        app(EarlyWarningService::class)->synchronize($student, $section, $this->riskSummary());
        $case = StudentIntervention::firstOrFail();

        $this->actingAs($facilitator)->put("/facilitator/early-warning/{$case->id}", [
            'assigned_to' => $coordinator->id,
            'status' => 'resolved',
            'referral_type' => 'guidance_referral',
            'severity' => 'high',
            'notes' => 'Student and guardian conference completed.',
            'action_plan' => 'Weekly check-in and guided catch-up activities.',
            'follow_up_date' => now()->addWeek()->toDateString(),
            'resolution' => 'Attendance and submissions returned to target.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $case->refresh();
        $this->assertSame('resolved', $case->status);
        $this->assertSame($coordinator->id, $case->assigned_to);
        $this->assertNotNull($case->resolved_at);
        $this->assertDatabaseHas('intervention_activities', ['intervention_id' => $case->id, 'actor_id' => $facilitator->id, 'action' => 'resolved']);
        $this->actingAs($student)->put("/student/early-warning/{$case->id}", [])->assertForbidden();
    }

    public function test_case_escalates_when_the_student_has_no_required_improvement(): void
    {
        [, $coordinator, $facilitator, $student, $section] = $this->records();
        $service = app(EarlyWarningService::class);
        $service->synchronize($student, $section, $this->riskSummary());
        $case = StudentIntervention::firstOrFail();
        RiskThreshold::where('component_id', $section->component_id)->firstOrFail()->update(['escalation_days' => 1, 'minimum_improvement_points' => 10]);
        $case->forceFill(['created_at' => now()->subDays(2)])->save();

        $service->synchronize($student, $section, $this->riskSummary());

        $this->assertSame('escalated', $case->fresh()->status);
        $this->assertDatabaseHas('intervention_activities', ['intervention_id' => $case->id, 'action' => 'automatically_escalated']);
        foreach ([$student, $facilitator, $coordinator] as $recipient) {
            $this->assertDatabaseHas('student_notifications', ['user_id' => $recipient->id, 'source_id' => $case->id, 'title' => 'Early-warning case escalated']);
        }
    }

    public function test_changed_risk_metrics_create_history_without_duplicate_open_cases(): void
    {
        [, , , $student, $section] = $this->records();
        $service = app(EarlyWarningService::class);
        $service->synchronize($student, $section, $this->riskSummary());
        $improved = $this->riskSummary();
        $improved['attendance_rate'] = 70.0;
        $improved['current_percentage'] = 72.0;
        $improved['missing_count'] = 1;
        $service->synchronize($student, $section, $improved);

        $this->assertSame(2, StudentRiskSnapshot::count());
        $this->assertSame(1, StudentIntervention::count());
        $this->assertDatabaseHas('intervention_activities', ['action' => 'risk_updated']);
    }

    public function test_intervention_records_are_isolated_between_institutions(): void
    {
        [, , , $student, $section] = $this->records();
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $sourceInstitutionId = (int) $student->institution_id;
        $other = Institution::create(['name' => 'Other University', 'short_name' => 'OU', 'slug' => 'other-university-risk', 'code' => 'OUR', 'is_active' => true]);
        app(InstitutionProvisioner::class)->provision($other, $sourceInstitutionId);
        $foreign = app(InstitutionContext::class)->runFor($other->id, fn () => StudentIntervention::create([
            'student_id' => $student->id, 'section_id' => $section->id, 'status' => 'open',
            'referral_type' => 'academic_support', 'severity' => 'medium',
        ]));

        app(InstitutionContext::class)->set($sourceInstitutionId);
        $this->assertNull(StudentIntervention::find($foreign->id));

        $this->actingAs($admin)->get("/admin/early-warning/{$foreign->id}")->assertNotFound();
    }

    private function records(): array
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'must_complete_student_profile' => false, 'must_upload_student_documents' => false]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-EW', 'name' => 'Early Warning Section', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        return [$component, $coordinator, $facilitator, $student, $section];
    }

    private function riskSummary(): array
    {
        return ['attendance_rate' => 50.0, 'current_percentage' => 60.0, 'missing_count' => 3, 'overall_status' => 'at_risk'];
    }
}
