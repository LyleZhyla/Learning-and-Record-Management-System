<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class AiChatWidget extends Component
{
    public function render(): View|Closure|string
    {
        $user = auth()->user();
        $conversation = $user?->aiChatConversations()->latest('updated_at')->first();
        $messages = $conversation
            ? $conversation->messages()->latest('id')->limit(30)->get()->reverse()->values()
            : collect();

        return view('components.ai-chat-widget', [
            'conversation' => $conversation,
            'messages' => $messages,
            'isConfigured' => filled(config('services.openai.api_key')),
        ]);
    }
}
