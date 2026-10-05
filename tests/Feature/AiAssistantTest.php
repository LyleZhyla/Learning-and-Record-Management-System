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

    public function test_floating_chatbot_is_available_across_every_account_portal(): void
    {
        config(['services.openai.api_key' => 'test-key']);

        foreach ([
            'super_admin' => '/admin/dashboard',
            'nstp_admin' => '/nstp-admin/dashboard',
            'coordinator' => '/coordinator/dashboard',
            'facilitator' => '/facilitator/dashboard',
            'student' => '/student/dashboard',
        ] as $role => $path) {
            $user = User::factory()->create(['role' => $role, 'status' => 'active']);

            $this->actingAs($user)->get($path)
                ->assertOk()
                ->assertSee('data-ai-widget', false)
                ->assertSee('Open SNAPIE AI chat')
                ->assertSee('Ano ang puwedeng gawin ko sa platform?')
                ->assertSee('data-ai-widget-suggestion', false)
                ->assertSee('ai-chat-widget.js');
        }
    }

    public function test_widget_suggestions_fill_the_composer_and_return_for_a_new_chat(): void
    {
        $script = file_get_contents(public_path('js/ai-chat-widget.js'));

        $this->assertStringContainsString("event.target.closest('[data-ai-widget-suggestion]')", $script);
        $this->assertStringContainsString('textarea.value = suggestion.dataset.aiWidgetSuggestion;', $script);
        $this->assertStringContainsString('messages.replaceChildren(welcomeTemplate.content.cloneNode(true));', $script);
    }

    public function test_widget_shows_only_the_signed_in_users_latest_conversation(): void
    {
        config(['services.openai.api_key' => 'test-key']);
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $other = User::factory()->create(['role' => 'student', 'status' => 'active']);
        $conversation = AiChatConversation::create(['user_id' => $student->id, 'title' => 'My chat']);
        $otherConversation = AiChatConversation::create(['user_id' => $other->id, 'title' => 'Other chat']);
        AiChatMessage::create(['user_id' => $student->id, 'conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'Private answer for this student.']);
        AiChatMessage::create(['user_id' => $other->id, 'conversation_id' => $otherConversation->id, 'role' => 'assistant', 'content' => 'Another user private answer.']);

        $this->actingAs($student)->get('/student/dashboard')
            ->assertOk()
            ->assertSee('Private answer for this student.')
            ->assertDontSee('Another user private answer.');
    }

    public function test_full_ai_page_does_not_duplicate_the_floating_widget(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);

        $this->actingAs($student)->get('/ai-assistant')
            ->assertOk()
            ->assertDontSee('data-ai-widget', false);
    }

    public function test_full_ai_page_serializes_the_message_before_disabling_its_textarea(): void
    {
        $script = file_get_contents(public_path('js/ai-assistant.js'));
        $payloadPosition = strpos($script, 'const payload = new FormData(form);');
        $disabledPosition = strpos($script, 'input.disabled = true;');

        $this->assertNotFalse($payloadPosition);
        $this->assertNotFalse($disabledPosition);
        $this->assertLessThan($disabledPosition, $payloadPosition);
        $this->assertStringContainsString('body: payload', $script);
    }

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
                && $request['reasoning']['effort'] === 'minimal'
                && $request['text']['verbosity'] === 'low'
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

    public function test_api_errors_are_translated_into_actionable_chat_messages(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        config(['services.openai.api_key' => 'test-key']);

        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'error' => ['type' => 'insufficient_quota', 'code' => 'credit_balance_exhausted'],
        ], 429)]);

        $this->actingAs($student)->postJson('/ai-assistant', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI Assistant has reached its OpenAI usage or credit limit. Please contact the system administrator.');

        $this->assertDatabaseCount('ai_chat_messages', 0);
    }

    public function test_incomplete_api_response_without_text_does_not_store_the_message(): void
    {
        $student = User::factory()->create(['role' => 'student', 'status' => 'active']);
        config(['services.openai.api_key' => 'test-key']);
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => 'incomplete',
            'incomplete_details' => ['reason' => 'max_output_tokens'],
            'output' => [['type' => 'reasoning']],
        ])]);

        $this->actingAs($student)->postJson('/ai-assistant', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The AI Assistant could not finish its reply. Please send the message again.');

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
