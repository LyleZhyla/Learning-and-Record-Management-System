@unless(request()->routeIs('ai-assistant.*'))
@php
    $suggestedPrompts = [
        'Ano ang puwedeng gawin ko sa platform?' => 'Ipakita ang mga pangunahing feature ng platform at kung paano ko sila magagamit.',
        'Explain NSTP' => 'Ipaliwanag ang NSTP, CWTS, LTS, at ROTC sa simpleng paraan.',
        'Make a study plan' => 'Tulungan mo akong gumawa ng study plan para sa susunod kong NSTP assessment.',
        'Help with coursework' => 'Tulungan mo akong unawain at planuhin ang NSTP coursework ko.',
    ];
@endphp
<aside class="ai-chat-widget" data-ai-widget>
    <section class="ai-widget-panel" data-ai-widget-panel hidden aria-label="SNAPIE AI mini chat">
        <header class="ai-widget-header">
            <img class="ai-widget-avatar" src="{{ asset('images/characters/snapie-face.webp') }}" alt="" aria-hidden="true">
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
                <div class="ai-widget-welcome" data-ai-widget-welcome>
                    <img class="snapie-character" src="{{ asset('images/characters/snapie-ai-guide.webp') }}" alt="SNAPIE AI mascot with a tablet">
                    <strong>How can I help?</strong>
                    <p>I can help with these common tasks. Pick a suggestion to get started:</p>
                    <div class="ai-widget-suggestions" aria-label="Suggested questions">
                        @foreach($suggestedPrompts as $label => $prompt)
                            <button type="button" data-ai-widget-suggestion="{{ $prompt }}" @disabled(!$isConfigured)>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endforelse
        </div>

        <template data-ai-widget-welcome-template>
            <div class="ai-widget-welcome" data-ai-widget-welcome>
                <img class="snapie-character" src="{{ asset('images/characters/snapie-ai-guide.webp') }}" alt="SNAPIE AI mascot with a tablet">
                <strong>What would you like help with?</strong>
                <p>Choose a suggestion below or type your own question.</p>
                <div class="ai-widget-suggestions" aria-label="Suggested questions">
                    @foreach($suggestedPrompts as $label => $prompt)
                        <button type="button" data-ai-widget-suggestion="{{ $prompt }}" @disabled(!$isConfigured)>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </template>

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
        <img src="{{ asset('images/characters/snapie-face.webp') }}" alt="" aria-hidden="true">
        <span>AI</span>
    </button>
</aside>
<script src="{{ asset('js/ai-chat-widget.js') }}?v={{ filemtime(public_path('js/ai-chat-widget.js')) }}"></script>
@endunless
