<?php

namespace Tests\Feature;

use App\Models\AiChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_role_can_open_the_ai_assistant_from_communication(): void
    {
        config(['services.openai.api_key' => 'test-key']);

        foreach (array_keys(User::ROLE_LABELS) as $role) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->get('/ai-assistant')
                ->assertOk()
                ->assertSee('SNAPIE AI')
                ->assertSee('AI Assistant');
        }
    }

    public function test_user_can_receive_an_ai_response_and_keep_conversation_history(): void
    {
        config([
            'services.openai.api_key' => 'test-key',
            'services.openai.model' => 'gpt-5-mini',
        ]);
        Http::fake([
            'api.openai.com/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [['type' => 'output_text', 'text' => 'NSTP helps develop civic responsibility.']],
                ]],
            ]),
        ]);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->postJson('/ai-assistant', [
            'message' => 'What is NSTP?',
        ])->assertOk()
            ->assertJsonPath('user_message.content', 'What is NSTP?')
            ->assertJsonPath('assistant_message.content', 'NSTP helps develop civic responsibility.');

        $this->assertDatabaseHas('ai_chat_messages', [
            'user_id' => $student->id,
            'role' => 'user',
            'content' => 'What is NSTP?',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'user_id' => $student->id,
            'role' => 'assistant',
            'content' => 'NSTP helps develop civic responsibility.',
        ]);

        Http::assertSent(function (Request $request) use ($student): bool {
            return $request->url() === 'https://api.openai.com/v1/responses'
                && $request['model'] === 'gpt-5-mini'
                && $request['store'] === false
                && $request['input'][0]['content'] === 'What is NSTP?'
                && $request['safety_identifier'] === hash('sha256', 'smart-nstp-user-'.$student->id)
                && ! str_contains($request['instructions'], $student->email);
        });
    }

    public function test_missing_configuration_and_api_failures_do_not_store_messages(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        config(['services.openai.api_key' => null]);

        $this->actingAs($student)->get('/ai-assistant')
            ->assertOk()
            ->assertSee('AI Assistant needs configuration.');

        $this->actingAs($student)->postJson('/ai-assistant', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI Assistant is not configured yet. Add OPENAI_API_KEY to the server environment.');

        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => ['message' => 'Unavailable']], 500)]);

        $this->actingAs($student)->postJson('/ai-assistant', ['message' => 'Try again'])
            ->assertStatus(503);

        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_user_can_clear_only_their_own_ai_conversation(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $otherStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        AiChatMessage::create(['user_id' => $student->id, 'role' => 'user', 'content' => 'Mine']);
        AiChatMessage::create(['user_id' => $otherStudent->id, 'role' => 'user', 'content' => 'Keep this']);

        $this->actingAs($student)->delete('/ai-assistant')->assertRedirect();

        $this->assertDatabaseMissing('ai_chat_messages', ['user_id' => $student->id]);
        $this->assertDatabaseHas('ai_chat_messages', ['user_id' => $otherStudent->id, 'content' => 'Keep this']);
    }
}
