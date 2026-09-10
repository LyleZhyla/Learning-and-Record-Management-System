<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiAssessmentScoringTest extends TestCase
{
    use RefreshDatabase;

    private User $facilitator;

    private User $student;

    private Assessment $assessment;

    private AssessmentSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->student = User::factory()->create([
            'role' => 'student',
            'status' => 'active',
            'name' => 'Private Student Name',
            'email' => 'private.student@example.test',
        ]);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create([
            'component_id' => $component->id,
            'facilitator_id' => $this->facilitator->id,
            'code' => 'CWTS-AI',
            'name' => 'AI Review Section',
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'capacity' => 40,
            'status' => 'active',
        ]);
        NstpEnrollment::create([
            'student_id' => $this->student->id,
            'component_id' => $component->id,
            'section_id' => $section->id,
            'academic_year' => '2026-2027',
            'semester' => 'first',
            'status' => 'enrolled',
        ]);
        $this->assessment = Assessment::create([
            'section_id' => $section->id,
            'created_by' => $this->facilitator->id,
            'title' => 'Community Reflection',
            'type' => 'activity',
            'instructions' => 'Explain one community need and a realistic NSTP response.',
            'rubric' => 'Understanding: 50 points. Feasibility: 30 points. Clarity: 20 points.',
            'max_score' => 100,
            'weight' => 20,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->submission = AssessmentSubmission::create([
            'assessment_id' => $this->assessment->id,
            'student_id' => $this->student->id,
            'answer_text' => 'Our barangay needs waste segregation. Volunteers can conduct household orientations and set up labelled collection points.',
            'submitted_at' => now(),
        ]);
    }

    public function test_ai_suggestion_is_stored_without_becoming_an_official_score(): void
    {
        config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'gpt-5-mini']);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->fakeAiResponse())]);

        $this->actingAs($this->facilitator)
            ->get('/facilitator/assessments/'.$this->assessment->id)
            ->assertOk()
            ->assertSee('AI-assisted scoring')
            ->assertSee('Generate AI suggestion')
            ->assertSee('AI suggestions never become official scores');

        $this->actingAs($this->facilitator)
            ->post('/facilitator/assessments/'.$this->assessment->id.'/submissions/'.$this->submission->id.'/ai-score')
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->submission->refresh();
        $this->assertNull($this->submission->score);
        $this->assertSame('84.00', $this->submission->ai_suggested_score);
        $this->assertSame('82.00', $this->submission->ai_confidence);
        $this->assertCount(3, $this->submission->ai_breakdown['criteria']);
        $this->assertNull($this->submission->ai_approved_at);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $serialized = json_encode($payload);

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $payload['store'] === false
                && $payload['text']['format']['type'] === 'json_schema'
                && $payload['text']['format']['strict'] === true
                && $payload['safety_identifier'] === hash('sha256', 'smart-nstp-facilitator-'.$this->facilitator->id)
                && str_contains($serialized, $this->assessment->rubric)
                && str_contains($serialized, $this->submission->answer_text)
                && ! str_contains($serialized, $this->student->name)
                && ! str_contains($serialized, $this->student->email);
        });
    }

    public function test_facilitator_must_review_and_approve_ai_score_before_it_is_official(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->fakeAiResponse())]);
        $this->actingAs($this->facilitator)
            ->post('/facilitator/assessments/'.$this->assessment->id.'/submissions/'.$this->submission->id.'/ai-score');
        $this->submission->refresh();

        $this->actingAs($this->facilitator)
            ->put('/facilitator/assessments/'.$this->assessment->id.'/submissions/'.$this->submission->id.'/ai-score/approve', [
                'score' => 86,
                'feedback' => 'Reviewed: strong and feasible plan.',
                'suggestion_generated_at' => $this->submission->ai_generated_at->toIso8601String(),
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('assessment_submissions', [
            'id' => $this->submission->id,
            'score' => 86,
            'feedback' => 'Reviewed: strong and feasible plan.',
            'graded_by' => $this->facilitator->id,
            'ai_approved_by' => $this->facilitator->id,
        ]);
    }

    public function test_ai_scoring_can_review_an_uploaded_document(): void
    {
        Storage::fake('local');
        Storage::put('assessment-submissions/reflection.pdf', '%PDF-1.4 sample reflection');
        $this->submission->update([
            'answer_text' => null,
            'file_path' => 'assessment-submissions/reflection.pdf',
            'original_filename' => 'reflection.pdf',
        ]);
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->fakeAiResponse())]);

        $this->actingAs($this->facilitator)
            ->post('/facilitator/assessments/'.$this->assessment->id.'/submissions/'.$this->submission->id.'/ai-score')
            ->assertRedirect()
            ->assertSessionHas('status');

        Http::assertSent(function (Request $request): bool {
            $content = $request->data()['input'][0]['content'];
            $file = collect($content)->firstWhere('type', 'input_file');

            return is_array($file)
                && $file['filename'] === 'reflection.pdf'
                && str_starts_with($file['file_data'], 'data:application/pdf;base64,');
        });
    }

    public function test_ai_scoring_requires_a_rubric_and_is_restricted_to_the_assigned_facilitator(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake();
        $this->assessment->update(['rubric' => null]);

        $this->actingAs($this->facilitator)
            ->post('/facilitator/assessments/'.$this->assessment->id.'/submissions/'.$this->submission->id.'/ai-score')
            ->assertSessionHasErrors('ai_scoring');
        Http::assertNothingSent();

        $otherFacilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->actingAs($otherFacilitator)
            ->post('/facilitator/assessments/'.$this->assessment->id.'/submissions/'.$this->submission->id.'/ai-score')
            ->assertForbidden();
    }

    public function test_resubmitting_work_clears_stale_ai_suggestions(): void
    {
        $this->submission->update([
            'ai_suggested_score' => 84,
            'ai_feedback' => 'Old suggestion',
            'ai_breakdown' => ['criteria' => [], 'needs_manual_review' => false],
            'ai_confidence' => 82,
            'ai_model' => 'gpt-5-mini',
            'ai_generated_at' => now(),
        ]);

        $this->actingAs($this->student)
            ->post('/student/assessments/'.$this->assessment->id.'/submit', ['answer_text' => 'A revised response.'])
            ->assertRedirect();

        $this->submission->refresh();
        $this->assertNull($this->submission->ai_suggested_score);
        $this->assertNull($this->submission->ai_generated_at);
        $this->assertNull($this->submission->score);
    }

    public function test_coordinator_can_only_view_ai_scoring_within_their_component(): void
    {
        $this->submission->update([
            'score' => 84,
            'feedback' => 'Approved feedback',
            'ai_suggested_score' => 84,
            'ai_feedback' => 'Good practical proposal.',
            'ai_breakdown' => [
                'criteria' => [[
                    'criterion' => 'Understanding',
                    'points_awarded' => 44,
                    'points_possible' => 50,
                    'evidence' => 'Identifies a specific community need.',
                ]],
                'needs_manual_review' => false,
            ],
            'ai_confidence' => 82,
            'ai_model' => 'gpt-5-mini',
            'ai_generated_at' => now(),
            'ai_approved_by' => $this->facilitator->id,
            'ai_approved_at' => now(),
        ]);
        $coordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $this->assessment->section->component_id,
        ]);

        $this->actingAs($coordinator)->get('/coordinator/assessments')
            ->assertOk()
            ->assertSee('View assessments')
            ->assertSee($this->assessment->title)
            ->assertDontSee('Create assessment');

        $this->actingAs($coordinator)->get('/coordinator/assessments/'.$this->assessment->id)
            ->assertOk()
            ->assertSee('Coordinator view only')
            ->assertSee('Official AI scoring rubric')
            ->assertSee('AI suggested 84.00')
            ->assertSee('82% confidence')
            ->assertSee('Approved feedback')
            ->assertDontSee('Generate AI suggestion')
            ->assertDontSee('Approve as official score')
            ->assertDontSee('Save manual score')
            ->assertDontSee('Save rubric');

        $this->assertFalse(Route::has('coordinator.assessments.ai-score.generate'));
        $this->assertFalse(Route::has('coordinator.assessments.ai-score.approve'));
        $this->assertFalse(Route::has('coordinator.assessments.rubric.update'));

        $otherComponent = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $otherCoordinator = User::factory()->create([
            'role' => 'coordinator',
            'status' => 'active',
            'nstp_component_id' => $otherComponent->id,
        ]);
        $this->actingAs($otherCoordinator)
            ->get('/coordinator/assessments/'.$this->assessment->id)
            ->assertForbidden();
    }

    private function fakeAiResponse(): array
    {
        return [
            'model' => 'gpt-5-mini',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'criteria' => [
                            ['criterion' => 'Understanding', 'points_awarded' => 44, 'points_possible' => 50, 'evidence' => 'Identifies a specific community need.'],
                            ['criterion' => 'Feasibility', 'points_awarded' => 24, 'points_possible' => 30, 'evidence' => 'Proposes realistic volunteer actions.'],
                            ['criterion' => 'Clarity', 'points_awarded' => 16, 'points_possible' => 20, 'evidence' => 'The response is concise and understandable.'],
                        ],
                        'total_score' => 84,
                        'feedback' => 'Good practical proposal. Add a simple implementation timeline.',
                        'confidence' => 82,
                        'needs_manual_review' => false,
                    ]),
                ]],
            ]],
        ];
    }
}
