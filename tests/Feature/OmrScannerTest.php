<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\OmrSheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OmrScannerTest extends TestCase
{
    use RefreshDatabase;

    private User $facilitator;

    private User $coordinator;

    private User $student;

    private NstpSection $section;

    private Assessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->coordinator = User::factory()->create(['role' => 'coordinator', 'status' => 'active']);
        $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'default_section_capacity' => 40, 'is_active' => true]);
        $this->section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $this->facilitator->id, 'code' => 'CWTS-01', 'name' => 'Section 1', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $this->student->id, 'component_id' => $component->id, 'section_id' => $this->section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);
        $this->assessment = Assessment::create(['section_id' => $this->section->id, 'created_by' => $this->facilitator->id, 'title' => 'Paper Quiz', 'type' => 'quiz', 'max_score' => 100, 'weight' => 20, 'status' => 'published', 'published_at' => now()]);
    }

    public function test_facilitator_can_create_answer_key_and_open_camera_scanner(): void
    {
        $response = $this->actingAs($this->facilitator)->post('/facilitator/answer-sheet-scanner', [
            'assessment_id' => $this->assessment->id,
            'item_count' => 4,
            'choice_count' => 4,
            'answers' => ['A', 'B', 'C', 'D'],
        ]);

        $sheet = OmrSheet::firstOrFail();
        $response->assertRedirect('/facilitator/answer-sheet-scanner/'.$sheet->id);
        $this->actingAs($this->facilitator)->get('/facilitator/answer-sheet-scanner/'.$sheet->id)
            ->assertOk()->assertSee('data-omr-video', false)->assertSee('Capture & read', false);
        $this->actingAs($this->facilitator)->get('/facilitator/answer-sheet-scanner/'.$sheet->id.'/print')
            ->assertOk()->assertSee('SNAPIE ANSWER SHEET')->assertSee('<svg', false);
    }

    public function test_facilitator_can_create_a_quiz_and_answer_sheet_together(): void
    {
        $this->actingAs($this->facilitator)->get('/facilitator/assessments/create')
            ->assertOk()->assertSee('Create an answer sheet?');

        $response = $this->actingAs($this->facilitator)->post('/facilitator/assessments', [
            'section_id' => $this->section->id,
            'title' => 'Integrated Quiz',
            'type' => 'quiz',
            'max_score' => 20,
            'status' => 'published',
            'create_answer_sheet' => 1,
            'item_count' => 5,
            'choice_count' => 4,
            'answers' => ['A', 'B', 'C', 'D', 'A'],
        ]);

        $assessment = Assessment::where('title', 'Integrated Quiz')->firstOrFail();
        $sheet = OmrSheet::where('assessment_id', $assessment->id)->firstOrFail();
        $response->assertRedirect('/facilitator/answer-sheet-scanner/'.$sheet->id);
        $this->assertSame(['A', 'B', 'C', 'D', 'A'], $sheet->answer_key);
    }

    public function test_coordinator_can_choose_to_create_an_exam_without_an_answer_sheet(): void
    {
        $this->actingAs($this->coordinator)->get('/coordinator/assessments/create')
            ->assertOk()->assertSee('Create an answer sheet?');

        $response = $this->actingAs($this->coordinator)->post('/coordinator/assessments', [
            'section_id' => $this->section->id,
            'title' => 'Exam Without Sheet',
            'type' => 'exam',
            'max_score' => 50,
            'status' => 'draft',
            'create_answer_sheet' => 0,
        ]);

        $assessment = Assessment::where('title', 'Exam Without Sheet')->firstOrFail();
        $response->assertRedirect('/coordinator/answer-sheet-scanner');
        $this->assertDatabaseMissing('omr_sheets', ['assessment_id' => $assessment->id]);
    }

    public function test_coordinator_can_scan_and_score_student_answer_sheet(): void
    {
        $sheet = OmrSheet::create(['assessment_id' => $this->assessment->id, 'created_by' => $this->facilitator->id, 'item_count' => 4, 'choice_count' => 4, 'answer_key' => ['A', 'B', 'C', 'D']]);

        $this->actingAs($this->coordinator)->get('/coordinator/answer-sheet-scanner/'.$sheet->id)->assertOk()->assertSee('Live paper scanner');
        $this->actingAs($this->coordinator)->postJson('/coordinator/answer-sheet-scanner/'.$sheet->id.'/grade', [
            'student_id' => $this->student->id,
            'answers' => ['A', 'B', 'C', 'A'],
            'confidence' => 92.5,
        ])->assertOk()->assertJsonPath('correct', 3)->assertJsonPath('score', 75);

        $this->assertDatabaseHas('omr_scan_results', ['omr_sheet_id' => $sheet->id, 'student_id' => $this->student->id, 'correct_count' => 3, 'score' => 75]);
        $this->assertDatabaseHas('assessment_submissions', ['assessment_id' => $this->assessment->id, 'student_id' => $this->student->id, 'score' => 75, 'graded_by' => $this->coordinator->id]);
    }

    public function test_other_roles_and_unassigned_facilitators_cannot_use_scanner(): void
    {
        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $sheet = OmrSheet::create(['assessment_id' => $this->assessment->id, 'created_by' => $this->facilitator->id, 'item_count' => 2, 'choice_count' => 2, 'answer_key' => ['A', 'B']]);

        $this->actingAs($otherFacilitator)->get('/facilitator/answer-sheet-scanner/'.$sheet->id)->assertForbidden();
        $this->actingAs($this->student)->get('/student/answer-sheet-scanner')->assertNotFound();
    }
}
