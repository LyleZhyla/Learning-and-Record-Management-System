<?php

namespace Tests\Feature;

use App\Models\AcademicTerm;
use App\Models\AcademicTermSnapshot;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\CompletionClearance;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\TermGradeApproval;
use App\Models\User;
use App\Services\AcademicTermLifecycleService;
use App\Services\ChedSemestralReportService;
use App\Services\GradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AcademicTermFinalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_term_follows_formal_lifecycle_with_coordinator_approval_clearance_and_snapshot(): void
    {
        [$admin, $coordinator, , $student, $section, $enrollment, $term] = $this->records();
        $service = app(AcademicTermLifecycleService::class);

        $service->transition($term, 'active', $admin);
        $service->transition($term->fresh(), 'grading', $admin);
        $service->approveSection($term->fresh(), $section, $coordinator, 'All official scores verified.');
        $service->transition($term->fresh(), 'finalized', $admin);

        $term->refresh();
        $this->assertSame('finalized', $term->status);
        $this->assertNotNull($term->finalized_at);
        $this->assertDatabaseHas('term_grade_approvals', ['academic_term_id' => $term->id, 'section_id' => $section->id, 'approved_by' => $coordinator->id]);
        $this->assertDatabaseHas('completion_clearances', ['academic_term_id' => $term->id, 'enrollment_id' => $enrollment->id, 'status' => 'cleared']);
        $snapshot = AcademicTermSnapshot::firstOrFail();
        $this->assertSame(hash('sha256', json_encode($snapshot->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), $snapshot->checksum);
        $this->assertSame($student->email, data_get($snapshot->payload, 'rows.0.student.email'));
        $service->transition($term->fresh(), 'archived', $admin, 'Retention lifecycle completed.');
        $this->assertSame('archived', $term->fresh()->status);
    }

    public function test_finalized_term_locks_attendance_assessments_grades_and_student_submissions(): void
    {
        [$admin, $coordinator, $facilitator, $student, $section, , $term, $assessment] = $this->records();
        $service = app(AcademicTermLifecycleService::class);
        $service->transition($term, 'active', $admin);
        $service->transition($term->fresh(), 'grading', $admin);
        $service->approveSection($term->fresh(), $section, $coordinator, null);
        $service->transition($term->fresh(), 'finalized', $admin);

        $this->actingAs($facilitator)->post('/facilitator/attendance', [
            'section_id' => $section->id, 'title' => 'Locked session', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(),
        ])->assertSessionHasErrors('term');
        $this->actingAs($facilitator)->putJson('/facilitator/grades/'.$section->id.'/scores', [
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 95,
        ])->assertUnprocessable()->assertJsonValidationErrors('term');
        $this->actingAs($student)->post('/student/assessments/'.$assessment->id.'/submit', ['answer_text' => 'Late change'])
            ->assertSessionHasErrors('term');
    }

    public function test_grade_deadline_blocks_late_encoding_before_finalization(): void
    {
        [$admin, , $facilitator, $student, $section, , $term, $assessment] = $this->records();
        $service = app(AcademicTermLifecycleService::class);
        $service->transition($term, 'active', $admin);
        $service->transition($term->fresh(), 'grading', $admin);
        $term->update(['grade_submission_deadline' => now()->subMinute()]);

        $this->actingAs($facilitator)->putJson('/facilitator/grades/'.$section->id.'/scores', [
            'assessment_id' => $assessment->id, 'student_id' => $student->id, 'score' => 95,
        ])->assertUnprocessable()->assertJsonValidationErrors('term');
    }

    public function test_term_workspace_is_visible_to_managers_but_only_coordinator_approves_grades(): void
    {
        [$admin, $coordinator, , , $section, , $term] = $this->records();
        $nstpAdmin = User::factory()->create(['role' => 'nstp_admin', 'status' => 'active']);
        $service = app(AcademicTermLifecycleService::class);
        $service->transition($term, 'active', $admin);
        $service->transition($term->fresh(), 'grading', $admin);

        $this->actingAs($admin)->get('/admin/academic-terms')->assertOk()->assertSee('Formal records lifecycle');
        $this->actingAs($nstpAdmin)->get('/nstp-admin/academic-terms')->assertOk();
        $this->actingAs($coordinator)->get('/coordinator/academic-terms')->assertOk()->assertSee('Approve final grades');
        $this->actingAs($admin)->post("/admin/academic-terms/{$term->id}/sections/{$section->id}/approve")->assertForbidden();
        $this->actingAs($coordinator)->post("/coordinator/academic-terms/{$term->id}/sections/{$section->id}/approve")
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_reopening_requires_reason_preserves_old_snapshot_and_creates_a_new_version(): void
    {
        [$admin, $coordinator, , , $section, , $term] = $this->records();
        $service = app(AcademicTermLifecycleService::class);
        $service->transition($term, 'active', $admin);
        $service->transition($term->fresh(), 'grading', $admin);
        $service->approveSection($term->fresh(), $section, $coordinator, null);
        $service->transition($term->fresh(), 'finalized', $admin);
        $first = AcademicTermSnapshot::firstOrFail();

        $this->actingAs($admin)->post('/admin/academic-terms/'.$term->id.'/reopen', [
            'reason' => 'Correct a verified encoding discrepancy in the final grade sheet.',
            'grade_submission_deadline' => now()->addDay()->format('Y-m-d H:i:s'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('grading', $term->fresh()->status);
        $this->assertSame(0, TermGradeApproval::count());
        $this->assertDatabaseHas('academic_term_transitions', ['academic_term_id' => $term->id, 'from_status' => 'finalized', 'to_status' => 'grading']);

        $service->approveSection($term->fresh(), $section, $coordinator, 'Correction verified.');
        $service->transition($term->fresh(), 'finalized', $admin);
        $this->assertSame(2, AcademicTermSnapshot::count());
        $this->assertSame($first->checksum, $first->fresh()->checksum);
        $this->expectException(LogicException::class);
        $first->update(['checksum' => str_repeat('0', 64)]);
    }

    public function test_ched_report_uses_frozen_snapshot_instead_of_mutable_live_records(): void
    {
        [$admin, $coordinator, , $student, $section, , $term] = $this->records();
        $service = app(AcademicTermLifecycleService::class);
        $service->transition($term, 'active', $admin);
        $service->transition($term->fresh(), 'grading', $admin);
        $service->approveSection($term->fresh(), $section, $coordinator, null);
        $service->transition($term->fresh(), 'finalized', $admin);
        $filters = ['academic_year' => '2026-2027', 'semester' => 'first'];
        $before = app(ChedSemestralReportService::class)->report($filters)['rows']->first();

        $student->update(['name' => 'Changed After Finalization', 'email' => 'changed@example.test']);
        $after = app(ChedSemestralReportService::class)->report($filters)['rows']->first();

        $this->assertSame($before, $after);
        $this->assertNotSame('changed@example.test', $after['email']);
    }

    public function test_serial_processing_requires_finalized_completion_clearance(): void
    {
        [$admin, $coordinator, , , $section, $enrollment, $term] = $this->records();
        $this->assertDatabaseMissing('completion_clearances', ['enrollment_id' => $enrollment->id]);
        $service = app(AcademicTermLifecycleService::class);
        $service->transition($term, 'active', $admin);
        $service->transition($term->fresh(), 'grading', $admin);
        $service->approveSection($term->fresh(), $section, $coordinator, null);
        $service->transition($term->fresh(), 'finalized', $admin);

        $clearance = CompletionClearance::where('enrollment_id', $enrollment->id)->firstOrFail();
        $this->assertSame('cleared', $clearance->status);
        $this->assertNotNull($clearance->cleared_at);
    }

    private function records(): array
    {
        $admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active', 'nstp_component_id' => $component->id]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active', 'must_complete_student_profile' => false, 'must_upload_student_documents' => false]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $facilitator->id, 'code' => 'CWTS-FINAL', 'name' => 'Finalization Section', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        $enrollment = NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        app(GradeService::class)->summary($student, $section->id);
        $assessment = null;
        foreach ($section->gradingCategories as $category) {
            $item = Assessment::create(['section_id' => $section->id, 'grading_category_id' => $category->id, 'created_by' => $facilitator->id, 'title' => $category->name.' Final Item', 'type' => 'activity', 'max_score' => 100, 'weight' => 100, 'status' => 'published', 'published_at' => now()]);
            AssessmentSubmission::create(['assessment_id' => $item->id, 'student_id' => $student->id, 'submitted_at' => now(), 'score' => 90, 'graded_by' => $facilitator->id, 'graded_at' => now()]);
            $assessment ??= $item;
        }
        $term = AcademicTerm::where('academic_year', '2026-2027')->where('semester', 'first')->firstOrFail();
        $term->update(['status' => 'open', 'grade_submission_deadline' => now()->addDay()]);

        return [$admin, $coordinator, $facilitator, $student, $section, $enrollment, $term, $assessment];
    }
}
