<?php

namespace Tests\Feature;

use App\Models\CommunityProject;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTaskMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_facilitator_can_assign_task_with_deadline_and_student_can_submit_accomplishment(): void
    {
        Storage::fake('local');
        [$project, $student, $facilitator] = $this->approvedProject();

        $this->actingAs($facilitator)->post('/facilitator/community-projects/'.$project->id.'/tasks', [
            'title' => 'Conduct beneficiary orientation',
            'description' => 'Orient participants and record attendance.',
            'assigned_to' => $student->id,
            'priority' => 'high',
            'due_at' => '2026-11-15 17:00:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task = ProjectTask::firstOrFail();
        $this->assertSame('pending', $task->status);
        $this->assertSame(0, $task->progress_percentage);
        $this->assertSame($student->id, $task->assigned_to);

        $this->actingAs($student)->put('/student/task-monitoring/'.$task->id.'/submit', [
            'status' => 'submitted',
            'progress_percentage' => 80,
            'submission_notes' => 'Orientation is almost complete.',
        ])->assertSessionHasErrors('progress_percentage');

        $this->actingAs($student)->put('/student/task-monitoring/'.$task->id.'/submit', [
            'status' => 'submitted',
            'progress_percentage' => 100,
            'submission_notes' => 'Orientation completed for 40 participants.',
            'evidence' => UploadedFile::fake()->create('orientation-report.pdf', 120, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('submitted', $task->status);
        $this->assertNotNull($task->submitted_at);
        Storage::disk('local')->assertExists($task->evidence_path);
        $this->actingAs($student)->get('/student/task-monitoring/'.$task->id.'/evidence')->assertOk();
    }

    public function test_authorized_staff_can_review_accomplishment_and_complete_task(): void
    {
        [$project, $student, $facilitator] = $this->approvedProject();
        $task = ProjectTask::create([
            'community_project_id' => $project->id,
            'title' => 'Distribute learning kits',
            'assigned_to' => $student->id,
            'assigned_by' => $facilitator->id,
            'priority' => 'normal',
            'status' => 'submitted',
            'progress_percentage' => 100,
            'submission_notes' => 'Fifty kits distributed and acknowledged.',
            'submitted_at' => now(),
        ]);

        $this->actingAs($facilitator)->put('/facilitator/task-monitoring/'.$task->id.'/review', [
            'decision' => 'complete',
            'review_notes' => 'Accomplishment verified against the distribution list.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(100, $task->progress_percentage);
        $this->assertSame($facilitator->id, $task->reviewed_by);
        $this->assertNotNull($task->completed_at);
    }

    public function test_students_only_see_assigned_tasks_and_dashboard_flags_overdue_work(): void
    {
        [$project, $student, $facilitator] = $this->approvedProject();
        $otherStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $task = ProjectTask::create([
            'community_project_id' => $project->id,
            'title' => 'Submit beneficiary attendance summary',
            'assigned_to' => $student->id,
            'assigned_by' => $facilitator->id,
            'priority' => 'urgent',
            'status' => 'pending',
            'due_at' => now()->subDay(),
            'progress_percentage' => 0,
        ]);

        $this->actingAs($student)->get('/student/task-monitoring')
            ->assertOk()->assertSee($task->title)->assertSee('Overdue');
        $this->actingAs($otherStudent)->get('/student/task-monitoring')
            ->assertOk()->assertDontSee($task->title);
        $this->actingAs($otherStudent)->put('/student/task-monitoring/'.$task->id.'/submit', [
            'status' => 'in_progress',
            'progress_percentage' => 10,
        ])->assertForbidden();
    }

    private function approvedProject(): array
    {
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'CWTS', 'is_active' => true]);
        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $facilitator->id,
            'code' => 'CWTS-01',
            'name' => 'CWTS Section 1',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        NstpEnrollment::create([
            'student_id' => $student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
        $project = CommunityProject::create([
            'reference_number' => 'CP-2026-00001',
            'title' => 'Barangay Learning Support Project',
            'component_id' => $component->id,
            'section_id' => $section->id,
            'proposed_by' => $student->id,
            'description' => 'Deliver community learning support.',
            'objectives' => 'Support learners with verified needs.',
            'beneficiaries' => 'Barangay learners',
            'beneficiary_count' => 50,
            'location' => 'Barangay Learning Center',
            'budget' => 10000,
            'approval_status' => 'approved',
            'approved_by' => $facilitator->id,
            'approved_at' => now(),
            'implementation_status' => 'planning',
        ]);

        return [$project, $student, $facilitator];
    }
}
