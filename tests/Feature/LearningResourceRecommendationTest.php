<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\LearningMaterial;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LearningResourceRecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_students_can_open_learning_recommendations(): void
    {
        $this->get('/student/learning-recommendations')->assertRedirect('/login');

        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->actingAs($facilitator)->get('/student/learning-recommendations')->assertForbidden();

        [$student] = $this->studentContext();
        $this->actingAs($student)->get('/student/learning-recommendations')
            ->assertOk()
            ->assertSee('Study the right material at the right time.')
            ->assertSee('Recommendation catalog')
            ->assertSee('AI Recommendations')
            ->assertSee('data-student-tour="recommendations"', false);
    }

    public function test_ai_recommends_only_materials_authorized_for_the_student(): void
    {
        config(['services.openai.api_key' => 'test-key', 'services.openai.model' => 'gpt-5-mini']);
        [$student, $visibleMaterial, $hiddenMaterial] = $this->studentContext();

        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'overview' => 'Begin with the needs-assessment resource before your next activity.',
                        'recommendations' => [
                            ['material_id' => $visibleMaterial->id, 'priority' => 'high', 'reason' => 'It supports the pending community activity.', 'study_action' => 'Read once, then create a five-point summary.'],
                            ['material_id' => $hiddenMaterial->id, 'priority' => 'high', 'reason' => 'Should be filtered.', 'study_action' => 'Do not show.'],
                        ],
                        'focus_areas' => ['Distinguish verified community needs from assumptions.'],
                        'study_plan' => [['title' => 'Review the guide', 'action' => 'Study for 30 minutes and write a short checklist.']],
                    ]),
                ]],
            ]],
        ])]);

        $this->actingAs($student)->post('/student/learning-recommendations', $this->preferences())
            ->assertOk()
            ->assertSee('Your recommended learning path')
            ->assertSee($visibleMaterial->title)
            ->assertSee('Read once, then create a five-point summary.')
            ->assertDontSee($hiddenMaterial->title)
            ->assertDontSee('Should be filtered.');

        Http::assertSent(function (Request $request) use ($student, $visibleMaterial, $hiddenMaterial): bool {
            $input = $request['input'][0]['content'][0]['text'];

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['store'] === false
                && $request['text']['format']['name'] === 'nstp_learning_resource_recommendations'
                && $request['safety_identifier'] === hash('sha256', 'smart-nstp-learning-'.$student->id)
                && str_contains($input, $visibleMaterial->title)
                && ! str_contains($input, $hiddenMaterial->title)
                && ! str_contains($input, 'Private submitted answer')
                && str_contains($request['instructions'], 'Recommend only material IDs');
        });
    }

    public function test_no_api_request_is_made_without_available_materials(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake();
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'code' => 'CWTS-EMPTY', 'name' => 'Empty', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        $this->actingAs($student)->from('/student/learning-recommendations')
            ->post('/student/learning-recommendations', $this->preferences())
            ->assertRedirect('/student/learning-recommendations')
            ->assertSessionHasErrors('recommendations');

        Http::assertNothingSent();
    }

    /** @return array{User, LearningMaterial, LearningMaterial} */
    private function studentContext(): array
    {
        $publisher = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $component = NstpComponent::create(['code' => 'CWTS', 'name' => 'Civic Welfare Training Service', 'is_active' => true]);
        $otherComponent = NstpComponent::create(['code' => 'LTS', 'name' => 'Literacy Training Service', 'is_active' => true]);
        $section = NstpSection::create(['component_id' => $component->id, 'facilitator_id' => $publisher->id, 'code' => 'CWTS-REC', 'name' => 'Recommendations', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        $otherSection = NstpSection::create(['component_id' => $component->id, 'code' => 'CWTS-HIDDEN', 'name' => 'Other section', 'academic_year' => '2026-2027', 'semester' => 'first', 'capacity' => 40, 'status' => 'active']);
        NstpEnrollment::create(['student_id' => $student->id, 'component_id' => $component->id, 'section_id' => $section->id, 'academic_year' => '2026-2027', 'semester' => 'first', 'status' => 'enrolled']);

        $visibleMaterial = LearningMaterial::create(['component_id' => $component->id, 'section_id' => $section->id, 'created_by' => $publisher->id, 'title' => 'Community Needs Assessment Guide', 'description' => 'A guide to gathering and validating community information.', 'external_url' => 'https://example.test/needs-guide', 'status' => 'published', 'published_at' => now()]);
        LearningMaterial::create(['component_id' => $component->id, 'created_by' => $publisher->id, 'title' => 'CWTS Orientation', 'description' => 'Component-wide orientation material.', 'external_url' => 'https://example.test/orientation', 'status' => 'published', 'published_at' => now()]);
        $hiddenMaterial = LearningMaterial::create(['component_id' => $component->id, 'section_id' => $otherSection->id, 'created_by' => $publisher->id, 'title' => 'Hidden Other Section Resource', 'external_url' => 'https://example.test/hidden', 'status' => 'published', 'published_at' => now()]);
        LearningMaterial::create(['component_id' => $otherComponent->id, 'created_by' => $publisher->id, 'title' => 'Hidden LTS Resource', 'external_url' => 'https://example.test/lts', 'status' => 'published', 'published_at' => now()]);

        $assessment = Assessment::create(['section_id' => $section->id, 'created_by' => $publisher->id, 'title' => 'Community Analysis', 'type' => 'activity', 'max_score' => 100, 'due_at' => now()->addWeek(), 'status' => 'published', 'published_at' => now()]);
        AssessmentSubmission::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'answer_text' => 'Private submitted answer', 'submitted_at' => now(), 'score' => 72, 'graded_by' => $publisher->id, 'graded_at' => now()]);

        return [$student, $visibleMaterial, $hiddenMaterial];
    }

    /** @return array<string, string> */
    private function preferences(): array
    {
        return [
            'study_goal' => 'prepare_assessment',
            'weekly_time' => '2_to_4',
            'learning_style' => 'mixed',
            'specific_goal' => 'Prepare for the community analysis activity.',
        ];
    }
}
