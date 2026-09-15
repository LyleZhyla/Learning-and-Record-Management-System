@extends($layout)

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
<section class="page-actions chat-page-heading">
    <div>
        <span class="eyebrow">Secure communication</span>
        <h2>{{ $isStaffChat ? 'Administrative staff chat' : 'Student–facilitator chat' }}</h2>
        <p>{{ $isStaffChat ? 'Private messaging for Super Admins, NSTP Admins, and Coordinators.' : 'Use private messages or section-based group chats with authorized participants.' }}</p>
    </div>
</section>

<section class="card chat-shell chat-shell-{{ $routePrefix }}">
    <aside class="chat-contacts" aria-label="Conversations">
        @if($routePrefix === 'facilitator')
            <details class="chat-group-creator" @if($errors->hasAny(['name', 'section_id', 'student_ids', 'student_ids.*'])) open @endif>
                <summary><span aria-hidden="true">＋</span> New group chat</summary>
                <form method="POST" action="{{ route('facilitator.messages.groups.store') }}" data-group-chat-form>
                    @csrf
                    <label>Group name<input name="name" maxlength="100" value="{{ old('name') }}" placeholder="e.g. Project Team A" required></label>
                    <label>Section
                        <select name="section_id" required data-group-section>
                            <option value="">Choose a section</option>
                            @foreach($availableSections as $availableSection)
                                <option value="{{ $availableSection->id }}" @selected((string) old('section_id') === (string) $availableSection->id)>{{ $availableSection->code }} · {{ $availableSection->semesterLabel() }}</option>
                            @endforeach
                        </select>
                    </label>
                    <fieldset>
                        <legend>Students</legend>
                        <div class="chat-group-students" data-group-students>
                            @forelse($availableSections as $availableSection)
                                @foreach($availableSection->enrollments as $enrollment)
                                    <label data-group-student data-section-id="{{ $availableSection->id }}" hidden>
                                        <input type="checkbox" name="student_ids[]" value="{{ $enrollment->student_id }}" @checked(in_array($enrollment->student_id, old('student_ids', [])))>
                                        <span>{{ $enrollment->student->name }}</span>
                                    </label>
                                @endforeach
                            @empty
                                <p>No assigned active sections.</p>
                            @endforelse
                            <p data-group-student-empty>Select a section to see its students.</p>
                        </div>
                    </fieldset>
                    @if($errors->hasAny(['name', 'section_id', 'student_ids', 'student_ids.*']))<small class="field-error">{{ $errors->first('name') ?: ($errors->first('section_id') ?: $errors->first('student_ids')) }}</small>@endif
                    <button class="primary-button" type="submit">Create group</button>
                </form>
            </details>
        @endif

        @if(!$isStaffChat)
            <div class="chat-list-label"><strong>Group chats</strong><span>{{ $groupChats->count() }}</span></div>
            <div class="chat-group-list">
                @forelse($groupChats as $groupChat)
                    <a class="chat-contact chat-group-contact {{ $activeGroup?->id === $groupChat->id ? 'active' : '' }}" href="{{ route($routePrefix.'.messages.groups.show', $groupChat) }}">
                        <span class="chat-avatar group">♟</span>
                        <span class="chat-contact-copy"><strong>{{ $groupChat->name }}</strong><small>{{ $groupChat->section->code }} · {{ $groupChat->members_count }} members</small></span>
                        @if($groupChat->unread_messages_count > 0)<span class="chat-unread" aria-label="{{ $groupChat->unread_messages_count }} unread group messages">{{ $groupChat->unread_messages_count > 99 ? '99+' : $groupChat->unread_messages_count }}</span>@endif
                    </a>
                @empty
                    <div class="chat-list-empty">No group chats yet.</div>
                @endforelse
            </div>
        @endif

        <div class="chat-contacts-heading">
            <strong>{{ $isStaffChat ? 'Staff contacts' : ($routePrefix === 'student' ? 'My facilitator' : 'My students') }}</strong>
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
                <div class="chat-no-contacts"><strong>No available conversation</strong><span>{{ $isStaffChat ? 'No other active administrative staff accounts are available.' : ($routePrefix === 'student' ? 'You need an active section with an assigned facilitator.' : 'A student will appear here after starting a conversation with you.') }}</span></div>
            @endforelse
        </div>
    </aside>

    <div class="chat-conversation">
        @if($activeGroup)
            <header class="chat-conversation-heading chat-group-heading">
                <span class="chat-avatar group">♟</span>
                <div><strong>{{ $activeGroup->name }}</strong><small>{{ $activeGroup->section->code }} · {{ $activeGroup->members->count() }} members</small></div>
            </header>

            <div class="chat-messages" data-chat-messages aria-live="polite">
                @forelse($groupMessages as $message)
                    <article class="chat-message {{ $message->sender_id === auth()->id() ? 'mine' : 'theirs' }}">
                        @if($message->sender_id !== auth()->id())<strong class="chat-message-sender">{{ $message->sender->name }}</strong>@endif
                        <div>{!! nl2br(e($message->body)) !!}</div>
                        <small>{{ $message->created_at->format('M d · h:i A') }}</small>
                    </article>
                @empty
                    <div class="chat-empty-thread"><strong>Start the group conversation</strong><span>Everyone in this group can read and reply.</span></div>
                @endforelse
            </div>

            <form class="chat-composer" method="POST" action="{{ route($routePrefix.'.messages.groups.messages.store', $activeGroup) }}">
                @csrf
                <label for="chat-body" class="sr-only">Group message</label>
                <textarea id="chat-body" name="body" rows="2" maxlength="2000" placeholder="Message {{ $activeGroup->name }}…" required>{{ old('body') }}</textarea>
                <button class="primary-button" type="submit">Send</button>
            </form>
        @elseif($contact && ($section || $isStaffChat))
            <header class="chat-conversation-heading">
                <span class="chat-avatar">{{ strtoupper(substr($contact->name, 0, 1)) }}</span>
                <div><strong>{{ $contact->name }}</strong><small>{{ $isStaffChat ? $contact->roleLabel() : $section->code.' · '.$section->semesterLabel().' · '.$section->academic_year }}</small></div>
            </header>

            <div class="chat-messages" data-chat-messages aria-live="polite">
                @forelse($messages as $message)
                    <article class="chat-message {{ $message->sender_id === auth()->id() ? 'mine' : 'theirs' }}">
                        <div>{!! nl2br(e($message->body)) !!}</div>
                        <small>{{ $message->created_at->format('M d · h:i A') }}@if($message->sender_id === auth()->id()) · {{ $message->read_at ? 'Read' : 'Sent' }}@endif</small>
                    </article>
                @empty
                    <div class="chat-empty-thread"><strong>Start the conversation</strong><span>{{ $isStaffChat ? 'Send a private administrative message.' : 'Send a message about your NSTP class or activities.' }}</span></div>
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
