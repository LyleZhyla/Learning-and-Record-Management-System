<?php

namespace Tests\Feature;

use App\Models\AiChatConversation;
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
                ->assertSee('AI Assistant')
                ->assertSee('Chat history')
                ->assertSee('New chat');
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

        $response = $this->actingAs($student)->postJson('/ai-assistant', [
            'message' => 'What is NSTP?',
        ])->assertOk()
            ->assertJsonPath('user_message.content', 'What is NSTP?')
            ->assertJsonPath('assistant_message.content', 'NSTP helps develop civic responsibility.')
            ->assertJsonPath('is_new_conversation', true);

        $conversation = AiChatConversation::firstOrFail();
        $this->assertSame('What is NSTP?', $conversation->title);
        $this->assertStringContainsString('/ai-assistant/'.$conversation->id, $response->json('conversation_url'));

        $this->assertDatabaseHas('ai_chat_messages', [
            'user_id' => $student->id,
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'What is NSTP?',
        ]);
        $this->assertDatabaseHas('ai_chat_messages', [
            'user_id' => $student->id,
            'conversation_id' => $conversation->id,
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

        $this->actingAs($student)->get('/ai-assistant/'.$conversation->id)
            ->assertOk()
            ->assertSee('What is NSTP?')
            ->assertSee('NSTP helps develop civic responsibility.');

        $this->actingAs($student)->postJson('/ai-assistant/'.$conversation->id, [
            'message' => 'Why is it important?',
        ])->assertOk()->assertJsonPath('is_new_conversation', false);

        $secondRequest = Http::recorded()[1][0];
        $this->assertCount(3, $secondRequest['input']);
        $this->assertSame('What is NSTP?', $secondRequest['input'][0]['content']);
        $this->assertSame('NSTP helps develop civic responsibility.', $secondRequest['input'][1]['content']);
        $this->assertSame('Why is it important?', $secondRequest['input'][2]['content']);
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

    public function test_user_can_delete_only_their_own_ai_conversation(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $otherStudent = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $conversation = AiChatConversation::create(['user_id' => $student->id, 'title' => 'Mine']);
        $otherConversation = AiChatConversation::create(['user_id' => $otherStudent->id, 'title' => 'Keep this']);
        AiChatMessage::create(['user_id' => $student->id, 'conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'Mine']);
        AiChatMessage::create(['user_id' => $otherStudent->id, 'conversation_id' => $otherConversation->id, 'role' => 'user', 'content' => 'Keep this']);

        $this->actingAs($student)->get('/ai-assistant/'.$otherConversation->id)->assertNotFound();
        $this->actingAs($student)->delete('/ai-assistant/conversations/'.$otherConversation->id)->assertNotFound();
        $this->actingAs($student)->delete('/ai-assistant/conversations/'.$conversation->id)->assertRedirect('/ai-assistant');

        $this->assertDatabaseMissing('ai_chat_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('ai_chat_messages', ['conversation_id' => $conversation->id]);
        $this->assertDatabaseHas('ai_chat_conversations', ['id' => $otherConversation->id]);
        $this->assertDatabaseHas('ai_chat_messages', ['user_id' => $otherStudent->id, 'content' => 'Keep this']);
    }
}
