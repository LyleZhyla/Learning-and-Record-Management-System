@extends($layout)

@section('title', 'AI Assistant')
@section('page-title', 'AI Assistant')

@section('content')
<section class="ai-assistant-layout">
    <aside class="card ai-history-panel">
        <div class="ai-history-heading"><div><span class="eyebrow">Your conversations</span><strong>Chat history</strong></div><a href="{{ route('ai-assistant.index', ['new' => 1]) }}">+ New chat</a></div>
        <div class="ai-history-list">
            @forelse($conversations as $history)
                <div class="ai-history-item {{ $conversation?->id === $history->id ? 'active' : '' }}">
                    <a href="{{ route('ai-assistant.index', ['conversation' => $history]) }}"><strong>{{ $history->title }}</strong><small>{{ $history->updated_at->diffForHumans() }}</small></a>
                    <form method="POST" action="{{ route('ai-assistant.destroy', ['conversation' => $history]) }}" data-ai-delete-form>@csrf @method('DELETE')<button type="submit" aria-label="Delete {{ $history->title }} conversation">×</button></form>
                </div>
            @empty
                <div class="ai-history-empty"><strong>No chat history yet</strong><span>Your previous AI conversations will appear here.</span></div>
            @endforelse
        </div>
    </aside>

    <div class="card ai-assistant-card">
        <header class="ai-assistant-heading">
            <div class="ai-orb" aria-hidden="true">✦</div>
            <div><span class="eyebrow">SNAPIE intelligence</span><h2>{{ $conversation?->title ?? 'New conversation' }}</h2><p>Ask about NSTP, CWTS, LTS, ROTC, coursework, or using the platform.</p></div>
        </header>

        @unless($isConfigured)
            <div class="alert warning ai-configuration-alert" role="alert"><strong>AI Assistant needs configuration.</strong> Add <code>OPENAI_API_KEY</code> to the server environment, then clear the Laravel configuration cache.</div>
        @endunless

        <div class="ai-suggestions" aria-label="Suggested questions">
            <button type="button" data-ai-suggestion="Ano ang pagkakaiba ng CWTS, LTS, at ROTC?">Compare NSTP components</button>
            <button type="button" data-ai-suggestion="Tulungan mo akong gumawa ng study plan para sa NSTP assessment.">Create a study plan</button>
            <button type="button" data-ai-suggestion="Explain the purpose of NSTP in simple terms.">Explain NSTP</button>
        </div>

        <div class="ai-message-list" data-ai-message-list aria-live="polite">
            @forelse($messages as $message)
                <article class="ai-message {{ $message->role === 'user' ? 'user' : 'assistant' }}">
                    <span class="ai-message-icon" aria-hidden="true">{{ $message->role === 'user' ? strtoupper(substr(auth()->user()->name, 0, 1)) : '✦' }}</span>
                    <div><strong>{{ $message->role === 'user' ? 'You' : 'SNAPIE AI' }}</strong><p>{!! nl2br(e($message->content)) !!}</p><small>{{ $message->created_at->format('M d · h:i A') }}</small></div>
                </article>
            @empty
                <div class="ai-welcome" data-ai-welcome><span>✦</span><strong>Welcome to SNAPIE AI</strong><p>I can explain concepts and help you study. I cannot change official records or make school decisions.</p></div>
            @endforelse
        </div>

        <div class="ai-typing" data-ai-typing hidden><i></i><i></i><i></i><span>SNAPIE AI is thinking…</span></div>

        <form class="ai-composer" method="POST" action="{{ route('ai-assistant.store', ['conversation' => $conversation]) }}" data-ai-form data-new-conversation="{{ $conversation ? '0' : '1' }}">
            @csrf
            <label class="sr-only" for="ai-message">Ask the AI Assistant</label>
            <textarea id="ai-message" name="message" rows="2" maxlength="2000" placeholder="Ask SNAPIE AI…" required @disabled(!$isConfigured)>{{ old('message') }}</textarea>
            <button class="primary-button" type="submit" @disabled(!$isConfigured)>Send <span aria-hidden="true">↑</span></button>
        </form>
        <p class="ai-disclaimer">AI can make mistakes. Verify official enrollment, attendance, grades, policies, and deadlines with authorized school personnel.</p>
    </div>
</section>

<script src="{{ asset('js/ai-assistant.js') }}"></script>
@endsection
