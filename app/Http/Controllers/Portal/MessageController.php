<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\NstpSection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request, ?User $contact = null): View
    {
        $actor = $request->user();
        $contacts = $this->contactQuery($actor)
            ->withCount(['sentChatMessages as unread_messages_count' => fn ($query) => $query
                ->where('recipient_id', $actor->id)
                ->whereNull('read_at')])
            ->orderBy('name')
            ->get();

        $contact ??= $contacts->first();
        $section = null;
        $messages = collect();

        if ($contact) {
            abort_unless($contacts->contains('id', $contact->id), 404);
            $section = $this->sharedSection($actor, $contact);
            abort_unless($section, 404);

            ChatMessage::query()
                ->where('sender_id', $contact->id)
                ->where('recipient_id', $actor->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $contacts->firstWhere('id', $contact->id)?->setAttribute('unread_messages_count', 0);

            $messages = ChatMessage::query()
                ->where('section_id', $section->id)
                ->where(fn ($query) => $query
                    ->where(fn ($pair) => $pair->where('sender_id', $actor->id)->where('recipient_id', $contact->id))
                    ->orWhere(fn ($pair) => $pair->where('sender_id', $contact->id)->where('recipient_id', $actor->id)))
                ->latest()
                ->limit(200)
                ->get()
                ->reverse()
                ->values();
        }

        $routePrefix = $actor->isStudent() ? 'student' : 'facilitator';

        return view('portal.messages.index', [
            'layout' => 'layouts.'.$routePrefix,
            'routePrefix' => $routePrefix,
            'contacts' => $contacts,
            'contact' => $contact,
            'section' => $section,
            'messages' => $messages,
        ]);
    }

    public function store(Request $request, User $recipient): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->contactQuery($actor)->whereKey($recipient)->exists(), 404);

        $section = $this->sharedSection($actor, $recipient);
        abort_unless($section, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        ChatMessage::create([
            'section_id' => $section->id,
            'sender_id' => $actor->id,
            'recipient_id' => $recipient->id,
            'body' => trim($validated['body']),
        ]);

        $routePrefix = $actor->isStudent() ? 'student' : 'facilitator';

        return redirect()->route($routePrefix.'.messages.index', ['contact' => $recipient])
            ->with('status', 'Message sent.');
    }

    private function contactQuery(User $actor): Builder
    {
        if ($actor->isStudent()) {
            return User::query()
                ->where('role', 'facilitator')
                ->where('status', 'active')
                ->whereHas('facilitatedSections', fn ($sections) => $sections
                    ->where('status', 'active')
                    ->whereHas('enrollments', fn ($enrollments) => $enrollments
                        ->where('student_id', $actor->id)
                        ->where('status', 'enrolled')));
        }

        abort_unless($actor->isFacilitator(), 403);

        return User::query()
            ->where('role', 'student')
            ->where('status', 'active')
            ->whereHas('nstpEnrollments', fn ($enrollments) => $enrollments
                ->where('status', 'enrolled')
                ->whereHas('section', fn ($sections) => $sections
                    ->where('facilitator_id', $actor->id)
                    ->where('status', 'active')));
    }

    private function sharedSection(User $actor, User $contact): ?NstpSection
    {
        $studentId = $actor->isStudent() ? $actor->id : $contact->id;
        $facilitatorId = $actor->isFacilitator() ? $actor->id : $contact->id;

        return NstpSection::query()
            ->where('facilitator_id', $facilitatorId)
            ->where('status', 'active')
            ->whereHas('enrollments', fn ($enrollments) => $enrollments
                ->where('student_id', $studentId)
                ->where('status', 'enrolled'))
            ->latest('academic_year')
            ->latest('id')
            ->first();
    }
}
