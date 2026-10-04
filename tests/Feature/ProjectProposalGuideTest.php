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
            ->assertSee('Turn a community need into a clearer project proposal.')
            ->assertSee('This tool does not approve proposals')
            ->assertSee('Proposal Guide')
            ->assertSee('data-student-tour="proposal"', false);
    }

    public function test_required_proposal_context_is_validated_before_using_ai(): void
    {
        Http::fake();
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->post('/student/project-proposal-guide', [
            'component' => 'INVALID',
            'project_title' => '',
            'community_need' => '',
            'target_beneficiaries' => '',
        ])->assertSessionHasErrors(['component', 'project_title', 'community_need', 'target_beneficiaries']);

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
                && $request['text']['format']['name'] === 'nstp_project_proposal_guidance'
                && $request['safety_identifier'] === hash('sha256', 'smart-nstp-proposal-'.$student->id)
                && str_contains($input, 'Barangay Reading Buddies')
                && str_contains($request['instructions'], 'Do not approve or reject the proposal.');
        });
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
            ->assertSessionHasInput('project_title', 'Barangay Reading Buddies');
    }

    /** @return array<string, string> */
    private function validProposal(): array
    {
        return [
            'component' => 'LTS',
            'project_title' => 'Barangay Reading Buddies',
            'community_need' => 'Some elementary learners need additional guided reading practice.',
            'target_beneficiaries' => 'Twenty elementary learners in a nearby partner barangay.',
            'proposed_objectives' => 'Improve reading confidence through guided practice.',
            'proposed_activities' => 'Consultation, baseline activity, reading sessions, and reflection.',
            'timeline' => 'Four Saturdays during the semester.',
            'available_resources' => 'Student volunteers and donated reading materials; permissions still need confirmation.',
        ];
    }
}
