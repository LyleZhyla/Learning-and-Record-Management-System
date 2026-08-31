@extends($layout)

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
<section class="page-actions chat-page-heading">
    <div>
        <span class="eyebrow">Private communication</span>
        <h2>Student–facilitator chat</h2>
        <p>Messages are limited to students and facilitators who share an active NSTP section.</p>
    </div>
</section>

<section class="card chat-shell chat-shell-{{ $routePrefix }}">
    <aside class="chat-contacts" aria-label="Conversations">
        <div class="chat-contacts-heading">
            <strong>{{ $routePrefix === 'student' ? 'My facilitator' : 'My students' }}</strong>
            <span>{{ $contacts->count() }} contact(s)</span>
        </div>
        <div class="chat-contact-list">
            @forelse($contacts as $person)
                <a class="chat-contact {{ $contact?->id === $person->id ? 'active' : '' }}" href="{{ route($routePrefix.'.messages.index', ['contact' => $person]) }}">
                    <span class="chat-avatar">{{ strtoupper(substr($person->name, 0, 1)) }}</span>
                    <span class="chat-contact-copy"><strong>{{ $person->name }}</strong><small>{{ $person->roleLabel() }}</small></span>
                    @if($person->unread_messages_count > 0)<span class="chat-unread" aria-label="{{ $person->unread_messages_count }} unread messages">{{ $person->unread_messages_count > 99 ? '99+' : $person->unread_messages_count }}</span>@endif
                </a>
            @empty
                <div class="chat-no-contacts"><strong>No available conversation</strong><span>{{ $routePrefix === 'student' ? 'You need an active section with an assigned facilitator.' : 'A student will appear here after starting a conversation with you.' }}</span></div>
            @endforelse
        </div>
    </aside>

    <div class="chat-conversation">
        @if($contact && $section)
            <header class="chat-conversation-heading">
                <span class="chat-avatar">{{ strtoupper(substr($contact->name, 0, 1)) }}</span>
                <div><strong>{{ $contact->name }}</strong><small>{{ $section->code }} · {{ $section->semesterLabel() }} · {{ $section->academic_year }}</small></div>
            </header>

            <div class="chat-messages" data-chat-messages aria-live="polite">
                @forelse($messages as $message)
                    <article class="chat-message {{ $message->sender_id === auth()->id() ? 'mine' : 'theirs' }}">
                        <div>{!! nl2br(e($message->body)) !!}</div>
                        <small>{{ $message->created_at->format('M d · h:i A') }}@if($message->sender_id === auth()->id()) · {{ $message->read_at ? 'Read' : 'Sent' }}@endif</small>
                    </article>
                @empty
                    <div class="chat-empty-thread"><strong>Start the conversation</strong><span>Send a message about your NSTP class or activities.</span></div>
                @endforelse
            </div>

            <form class="chat-composer" method="POST" action="{{ route($routePrefix.'.messages.store', ['recipient' => $contact]) }}">
                @csrf
                <label for="chat-body" class="sr-only">Message</label>
                <textarea id="chat-body" name="body" rows="2" maxlength="2000" placeholder="Write a message…" required>{{ old('body') }}</textarea>
                <button class="primary-button" type="submit">Send</button>
            </form>
        @else
            <div class="chat-empty-panel"><span aria-hidden="true">◇</span><strong>No conversation selected</strong><p>Choose an available contact to start messaging.</p></div>
        @endif
    </div>
</section>

<script src="{{ asset('js/chat.js') }}"></script>
@endsection
