<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Services\OpenAiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class AiAssistantController extends Controller
{
    public function index(Request $request, ?AiChatConversation $conversation = null): View
    {
        $conversations = $request->user()->aiChatConversations()->latest('updated_at')->limit(50)->get();

        if ($request->boolean('new')) {
            $conversation = null;
        } elseif ($conversation) {
            abort_unless($conversation->user_id === $request->user()->id, 404);
        } else {
            $conversation = $conversations->first();
        }

        $messages = $conversation
            ? $conversation->messages()->oldest('id')->limit(200)->get()
            : collect();

        return view('portal.ai-assistant.index', [
            'layout' => 'layouts.'.$this->layoutName($request),
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
            'isConfigured' => filled(config('services.openai.api_key')),
        ]);
    }

    public function store(Request $request, OpenAiAssistantService $assistant, ?AiChatConversation $conversation = null): JsonResponse|RedirectResponse
    {
        if ($conversation) {
            abort_unless($conversation->user_id === $request->user()->id, 404);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $message = trim($validated['message']);

        try {
            $answer = $assistant->reply($request->user(), $message, $conversation);
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            return back()->withErrors(['message' => $exception->getMessage()])->withInput();
        }

        $isNewConversation = $conversation === null;
        [$conversation, $userMessage, $assistantMessage] = DB::transaction(function () use ($request, $message, $answer, $conversation): array {
            $conversation ??= AiChatConversation::create([
                'user_id' => $request->user()->id,
                'title' => Str::limit($message, 55),
            ]);
            $userMessage = AiChatMessage::create([
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $message,
            ]);
            $assistantMessage = AiChatMessage::create([
                'user_id' => $request->user()->id,
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $answer,
            ]);

            $conversation->touch();

            return [$conversation, $userMessage, $assistantMessage];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'user_message' => $this->messagePayload($userMessage),
                'assistant_message' => $this->messagePayload($assistantMessage),
                'conversation_url' => route('ai-assistant.index', ['conversation' => $conversation]),
                'is_new_conversation' => $isNewConversation,
            ]);
        }

        return redirect()->route('ai-assistant.index', ['conversation' => $conversation])
            ->with('status', 'The AI Assistant replied.');
    }

    public function destroy(Request $request, AiChatConversation $conversation): RedirectResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
        $conversation->delete();

        return redirect()->route('ai-assistant.index')->with('status', 'AI conversation deleted.');
    }

    private function layoutName(Request $request): string
    {
        return match ($request->user()->role) {
            'super_admin' => 'admin',
            'nstp_admin' => 'nstp-admin',
            default => $request->user()->role,
        };
    }

    private function messagePayload(AiChatMessage $message): array
    {
        return [
            'role' => $message->role,
            'content' => $message->content,
            'time' => $message->created_at->format('M d · h:i A'),
        ];
    }
}
