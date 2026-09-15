<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ChatGroup;
use App\Models\ChatGroupMessage;
use App\Models\ChatMessage;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MessageController extends Controller
{
    private const STAFF_CHAT_ROLES = ['super_admin', 'nstp_admin', 'coordinator'];

    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request, ?User $contact = null): View
    {
        $actor = $request->user();
        $isStaffChat = $this->isStaffChatUser($actor);
        $contactsQuery = $this->contactQuery($actor)
            ->withCount(['sentChatMessages as unread_messages_count' => fn ($query) => $query
                ->where('recipient_id', $actor->id)
                ->whereNull('read_at')]);

        if ($actor->isFacilitator()) {
            $contactsQuery
                ->where(fn ($contacts) => $contacts
                    ->whereHas('sentChatMessages', fn ($messages) => $messages->where('recipient_id', $actor->id))
                    ->orWhereHas('receivedChatMessages', fn ($messages) => $messages->where('sender_id', $actor->id)))
                ->addSelect(['latest_chat_message_id' => ChatMessage::query()
                    ->selectRaw('MAX(chat_messages.id)')
                    ->where(fn ($messages) => $messages
                        ->where(fn ($pair) => $pair
                            ->whereColumn('chat_messages.sender_id', 'users.id')
                            ->where('chat_messages.recipient_id', $actor->id))
                        ->orWhere(fn ($pair) => $pair
                            ->whereColumn('chat_messages.recipient_id', 'users.id')
                            ->where('chat_messages.sender_id', $actor->id)))])
                ->orderByDesc('latest_chat_message_id');
        } else {
            $contactsQuery->orderBy('name');
        }

        $contacts = $contactsQuery->get();

        $contact ??= $contacts->first();
        $section = null;
        $messages = collect();

        if ($contact) {
            abort_unless($contacts->contains('id', $contact->id), 404);
            if (! $isStaffChat) {
                $section = $this->sharedSection($actor, $contact);
                abort_unless($section, 404);
            }

            ChatMessage::query()
                ->where('sender_id', $contact->id)
                ->where('recipient_id', $actor->id)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $contacts->firstWhere('id', $contact->id)?->setAttribute('unread_messages_count', 0);

            $messages = ChatMessage::query()
                ->when($isStaffChat, fn ($query) => $query->whereNull('section_id'))
                ->when(! $isStaffChat, fn ($query) => $query->where('section_id', $section->id))
                ->where(fn ($query) => $query
                    ->where(fn ($pair) => $pair->where('sender_id', $actor->id)->where('recipient_id', $contact->id))
                    ->orWhere(fn ($pair) => $pair->where('sender_id', $contact->id)->where('recipient_id', $actor->id)))
                ->latest()
                ->limit(200)
                ->get()
                ->reverse()
                ->values();
        }

        $routePrefix = $this->access->routePrefix($actor);

        return view('portal.messages.index', [
            'layout' => $this->access->layout($actor),
            'routePrefix' => $routePrefix,
            'isStaffChat' => $isStaffChat,
            'contacts' => $contacts,
            'contact' => $contact,
            'section' => $section,
            'messages' => $messages,
            'groupChats' => $this->groupChats($actor),
            'activeGroup' => null,
            'groupMessages' => collect(),
            'availableSections' => $this->availableGroupSections($actor),
        ]);
    }

    public function group(Request $request, ChatGroup $group): View
    {
        $actor = $request->user();
        $activeGroup = $this->groupQuery($actor)
            ->with(['section.component', 'members'])
            ->findOrFail($group->id);

        DB::table('chat_group_members')
            ->where('chat_group_id', $activeGroup->id)
            ->where('user_id', $actor->id)
            ->update(['last_read_at' => now(), 'updated_at' => now()]);

        $groupMessages = $activeGroup->messages()
            ->with('sender')
            ->latest()
            ->limit(300)
            ->get()
            ->reverse()
            ->values();

        $routePrefix = $this->access->routePrefix($actor);

        return view('portal.messages.index', [
            'layout' => $this->access->layout($actor),
            'routePrefix' => $routePrefix,
            'isStaffChat' => false,
            'contacts' => collect(),
            'contact' => null,
            'section' => null,
            'messages' => collect(),
            'groupChats' => $this->groupChats($actor),
            'activeGroup' => $activeGroup,
            'groupMessages' => $groupMessages,
            'availableSections' => $this->availableGroupSections($actor),
        ]);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->isFacilitator(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'section_id' => ['required', 'integer'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $section = NstpSection::query()
            ->whereKey($validated['section_id'])
            ->where('facilitator_id', $actor->id)
            ->where('status', 'active')
            ->firstOrFail();

        $studentIds = collect($validated['student_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $allowedStudentIds = User::query()
            ->whereKey($studentIds)
            ->where('role', 'student')
            ->where('status', 'active')
            ->whereHas('nstpEnrollments', fn ($enrollments) => $enrollments
                ->where('section_id', $section->id)
                ->where('status', 'enrolled'))
            ->pluck('id');

        if ($allowedStudentIds->count() !== $studentIds->count()) {
            throw ValidationException::withMessages([
                'student_ids' => 'Every selected student must be actively enrolled in the selected section.',
            ]);
        }

        $group = DB::transaction(function () use ($actor, $section, $validated, $allowedStudentIds): ChatGroup {
            $group = ChatGroup::create([
                'section_id' => $section->id,
                'facilitator_id' => $actor->id,
                'name' => trim($validated['name']),
            ]);

            $memberRows = $allowedStudentIds->push($actor->id)->unique()->mapWithKeys(fn ($userId) => [
                $userId => ['last_read_at' => now()],
            ])->all();
            $group->members()->attach($memberRows);

            return $group;
        });

        return redirect()->route('facilitator.messages.groups.show', $group)
            ->with('status', 'Group chat created.');
    }

    public function storeGroupMessage(Request $request, ChatGroup $group): RedirectResponse
    {
        $actor = $request->user();
        $group = $this->groupQuery($actor)->findOrFail($group->id);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($actor, $group, $validated): void {
            ChatGroupMessage::create([
                'chat_group_id' => $group->id,
                'sender_id' => $actor->id,
                'body' => trim($validated['body']),
            ]);
            DB::table('chat_group_members')
                ->where('chat_group_id', $group->id)
                ->where('user_id', $actor->id)
                ->update(['last_read_at' => now(), 'updated_at' => now()]);
        });

        $routePrefix = $this->access->routePrefix($actor);

        return redirect()->route($routePrefix.'.messages.groups.show', $group)
            ->with('status', 'Message sent.');
    }

    public function store(Request $request, User $recipient): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($this->contactQuery($actor)->whereKey($recipient)->exists(), 404);

        $isStaffChat = $this->isStaffChatUser($actor);
        $section = $isStaffChat ? null : $this->sharedSection($actor, $recipient);
        abort_unless($isStaffChat || $section, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        ChatMessage::create([
            'section_id' => $section?->id,
            'sender_id' => $actor->id,
            'recipient_id' => $recipient->id,
            'body' => trim($validated['body']),
        ]);

        $routePrefix = $this->access->routePrefix($actor);

        return redirect()->route($routePrefix.'.messages.index', ['contact' => $recipient])
            ->with('status', 'Message sent.');
    }

    private function contactQuery(User $actor): Builder
    {
        if ($this->isStaffChatUser($actor)) {
            return User::query()
                ->whereKeyNot($actor->id)
                ->whereIn('role', self::STAFF_CHAT_ROLES)
                ->where('status', 'active');
        }

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

    private function groupQuery(User $actor): Builder
    {
        abort_unless($actor->isFacilitator() || $actor->isStudent(), 403);

        return ChatGroup::query()
            ->whereHas('members', fn ($members) => $members->whereKey($actor->id))
            ->when($actor->isFacilitator(), fn ($groups) => $groups->where('facilitator_id', $actor->id))
            ->when($actor->isStudent(), fn ($groups) => $groups
                ->whereHas('section.enrollments', fn ($enrollments) => $enrollments
                    ->where('student_id', $actor->id)
                    ->where('status', 'enrolled')));
    }

    private function groupChats(User $actor): \Illuminate\Support\Collection
    {
        if (! $actor->isFacilitator() && ! $actor->isStudent()) {
            return collect();
        }

        return $this->groupQuery($actor)
            ->with(['section', 'members' => fn ($members) => $members->whereKey($actor->id)])
            ->withCount('members')
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->latest('id')
            ->get()
            ->each(function (ChatGroup $group) use ($actor): void {
                $lastReadAt = $group->members->first()?->pivot?->last_read_at;
                $group->setAttribute('unread_messages_count', $group->messages()
                    ->where('sender_id', '!=', $actor->id)
                    ->when($lastReadAt, fn ($messages) => $messages->where('created_at', '>', $lastReadAt))
                    ->count());
            });
    }

    private function availableGroupSections(User $actor): \Illuminate\Support\Collection
    {
        if (! $actor->isFacilitator()) {
            return collect();
        }

        return NstpSection::query()
            ->where('facilitator_id', $actor->id)
            ->where('status', 'active')
            ->with(['component', 'enrollments' => fn ($enrollments) => $enrollments
                ->where('status', 'enrolled')
                ->whereHas('student', fn ($students) => $students->where('status', 'active'))
                ->with('student')])
            ->orderBy('code')
            ->get();
    }

    private function isStaffChatUser(User $user): bool
    {
        return in_array($user->role, self::STAFF_CHAT_ROLES, true);
    }
}
