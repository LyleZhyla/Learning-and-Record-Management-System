<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\AiChatMessage;
use App\Services\OpenAiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;

class AiAssistantController extends Controller
{
    public function index(Request $request): View
    {
        $messages = $request->user()->aiChatMessages()
            ->latest()
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return view('portal.ai-assistant.index', [
            'layout' => 'layouts.'.$this->layoutName($request),
            'messages' => $messages,
            'isConfigured' => filled(config('services.openai.api_key')),
        ]);
    }

    public function store(Request $request, OpenAiAssistantService $assistant): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $message = trim($validated['message']);

        try {
            $answer = $assistant->reply($request->user(), $message);
        } catch (RuntimeException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            return back()->withErrors(['message' => $exception->getMessage()])->withInput();
        }

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($request, $message, $answer): array {
            $userMessage = AiChatMessage::create([
                'user_id' => $request->user()->id,
                'role' => 'user',
                'content' => $message,
            ]);
            $assistantMessage = AiChatMessage::create([
                'user_id' => $request->user()->id,
                'role' => 'assistant',
                'content' => $answer,
            ]);

            return [$userMessage, $assistantMessage];
        });

        if ($request->expectsJson()) {
            return response()->json([
                'user_message' => $this->messagePayload($userMessage),
                'assistant_message' => $this->messagePayload($assistantMessage),
            ]);
        }

        return back()->with('status', 'The AI Assistant replied.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->aiChatMessages()->delete();

        return back()->with('status', 'AI conversation cleared.');
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
