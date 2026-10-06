<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProjectProposalGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_students_can_open_the_project_proposal_guide(): void
    {
        $this->get('/student/project-proposal-guide')->assertRedirect('/login');

        $facilitator = User::factory()->create(['role' => 'facilitator', 'status' => 'active']);
        $this->actingAs($facilitator)->get('/student/project-proposal-guide')->assertForbidden();

        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $this->actingAs($student)->get('/student/project-proposal-guide')
            ->assertOk()
            ->assertSee('Turn your project idea into a clearer proposal.')
            ->assertSee('A short project idea is enough.')
            ->assertSee('This tool does not approve proposals')
            ->assertSee('Proposal Guide')
            ->assertSee('data-student-tour="proposal"', false);
    }

    public function test_only_the_project_idea_is_required_before_using_ai(): void
    {
        Http::fake();
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->post('/student/project-proposal-guide', [
            'project_idea' => '',
        ])->assertSessionHasErrors(['project_idea']);

        Http::assertNothingSent();
    }

    public function test_student_receives_structured_advisory_proposal_guidance(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-5-mini',
        ]);
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'summary' => 'The idea addresses a relevant literacy need but needs verified baseline data.',
                        'strengths' => ['The target learners are clearly identified.'],
                        'recommendations' => ['Confirm the reading-level baseline with the partner school.'],
                        'suggested_objectives' => ['Deliver four guided reading sessions to the agreed learner group.'],
                        'suggested_activities' => [[
                            'activity' => 'Baseline consultation',
                            'purpose' => 'Confirm needs, permissions, and an appropriate reading-level measure.',
                        ]],
                        'risks' => ['Parental consent and learner privacy must be verified.'],
                        'next_steps' => ['Discuss the revised plan with the NSTP facilitator.'],
                    ]),
                ]],
            ]],
        ])]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->post('/student/project-proposal-guide', $this->validProposal())
            ->assertOk()
            ->assertSee('Your proposal guidance')
            ->assertSee('The target learners are clearly identified.')
            ->assertSee('Baseline consultation')
            ->assertSee('Requires facilitator review')
            ->assertSee('This guidance is not an approval')
            ->assertSee('Barangay Reading Buddies');

        Http::assertSent(function (Request $request) use ($student): bool {
            $input = $request['input'][0]['content'][0]['text'];

            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['model'] === 'gpt-5-mini'
                && $request['store'] === false
                && $request['max_output_tokens'] === 5000
                && $request['text']['format']['name'] === 'nstp_project_proposal_guidance'
                && $request['safety_identifier'] === hash('sha256', 'smart-nstp-proposal-'.$student->id)
                && str_contains($input, 'Barangay Reading Buddies')
                && str_contains($request['instructions'], 'Do not approve or reject the proposal.');
        });
    }

    public function test_partial_ai_sections_are_shown_instead_of_rejecting_the_whole_guidance(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'summary' => 'Start with a consultation and verify the community need.',
                        'recommendations' => ['Ask the intended beneficiaries what support they need.'],
                    ]),
                ]],
            ]],
        ])]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->post('/student/project-proposal-guide', [
            'project_idea' => 'Community clean-up drive',
        ])->assertOk()
            ->assertSee('Your proposal guidance')
            ->assertSee('Start with a consultation')
            ->assertSee('Ask the intended beneficiaries')
            ->assertDontSee('The AI returned incomplete proposal guidance');
    }

    public function test_token_limited_ai_response_is_retried_automatically(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fakeSequence()
            ->push([
                'status' => 'incomplete',
                'incomplete_details' => ['reason' => 'max_output_tokens'],
                'output' => [],
            ])
            ->push([
                'status' => 'completed',
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode(['summary' => 'A practical starter plan is ready.']),
                    ]],
                ]],
            ]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->post('/student/project-proposal-guide', [
            'project_idea' => 'Community vegetable garden',
        ])->assertOk()->assertSee('A practical starter plan is ready.');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request['max_output_tokens'] === 8000);
    }

    public function test_ai_failure_keeps_the_draft_and_shows_a_safe_error(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Unavailable']], 500)]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->from('/student/project-proposal-guide')
            ->post('/student/project-proposal-guide', $this->validProposal())
            ->assertRedirect('/student/project-proposal-guide')
            ->assertSessionHasErrors('proposal_guidance')
            ->assertSessionHasInput('project_idea', 'Barangay Reading Buddies reading program for elementary learners.');
    }

    /** @return array<string, string> */
    private function validProposal(): array
    {
        return [
            'project_idea' => 'Barangay Reading Buddies reading program for elementary learners.',
        ];
    }
}
