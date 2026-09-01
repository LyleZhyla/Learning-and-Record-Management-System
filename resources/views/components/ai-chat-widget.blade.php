@unless(request()->routeIs('ai-assistant.*'))
<aside class="ai-chat-widget" data-ai-widget>
    <section class="ai-widget-panel" data-ai-widget-panel hidden aria-label="SNAPIE AI mini chat">
        <header class="ai-widget-header">
            <span class="ai-widget-avatar" aria-hidden="true">✦</span>
            <div><strong>SNAPIE AI</strong><small>Ask without leaving this page</small></div>
            <button type="button" data-ai-widget-close aria-label="Close AI chat">×</button>
        </header>

        <div class="ai-widget-actions">
            <button type="button" data-ai-widget-new>+ New chat</button>
            <a data-ai-widget-expand href="{{ $conversation ? route('ai-assistant.index', ['conversation' => $conversation]) : route('ai-assistant.index', ['new' => 1]) }}">Open full assistant ↗</a>
        </div>

        @unless($isConfigured)
            <div class="ai-widget-configuration">AI Assistant is not configured yet. Please contact the system administrator.</div>
        @endunless

        <div class="ai-widget-messages" data-ai-widget-messages aria-live="polite">
            @forelse($messages as $message)
                <article class="ai-widget-message {{ $message->role === 'user' ? 'user' : 'assistant' }}">
                    <strong>{{ $message->role === 'user' ? 'You' : 'SNAPIE AI' }}</strong>
                    <p>{!! nl2br(e($message->content)) !!}</p>
                </article>
            @empty
                <div class="ai-widget-welcome" data-ai-widget-welcome><span>✦</span><strong>How can I help?</strong><p>Ask about NSTP, coursework, studying, or using the platform.</p></div>
            @endforelse
        </div>

        <div class="ai-widget-thinking" data-ai-widget-thinking hidden><i></i><i></i><i></i><span>Thinking…</span></div>
        <div class="ai-widget-error" data-ai-widget-error hidden role="alert"></div>

        <form method="POST" action="{{ route('ai-assistant.store', ['conversation' => $conversation]) }}" data-ai-widget-form data-new-action="{{ route('ai-assistant.store') }}">
            @csrf
            <label class="sr-only" for="ai-widget-message">Ask SNAPIE AI</label>
            <textarea id="ai-widget-message" name="message" rows="2" maxlength="2000" placeholder="Ask SNAPIE AI…" required @disabled(!$isConfigured)></textarea>
            <button type="submit" aria-label="Send message" @disabled(!$isConfigured)>↑</button>
        </form>
        <p class="ai-widget-disclaimer">AI can make mistakes. Verify official records with authorized personnel.</p>
    </section>

    <button class="ai-widget-launcher" type="button" data-ai-widget-toggle aria-label="Open SNAPIE AI chat" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3C6.8 3 2.5 6.7 2.5 11.3c0 2.6 1.4 4.9 3.6 6.4L5.4 21l4-1.8c.8.2 1.7.3 2.6.3 5.2 0 9.5-3.7 9.5-8.2S17.2 3 12 3Z"/><path d="M8.2 11.5h.1m3.7 0h.1m3.7 0h.1"/></svg>
        <span>AI</span>
    </button>
</aside>
<script src="{{ asset('js/ai-chat-widget.js') }}?v={{ filemtime(public_path('js/ai-chat-widget.js')) }}"></script>
@endunless
