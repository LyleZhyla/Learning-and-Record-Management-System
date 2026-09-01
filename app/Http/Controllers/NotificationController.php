<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\StudentNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function read(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($this->notifications->visibleQuery($request->user())->whereKey($announcement)->exists(), 404);
        DB::table('announcement_reads')->updateOrInsert(
            ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return back();
    }

    public function openAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($this->notifications->visibleQuery($request->user())->whereKey($announcement)->exists(), 404);
        DB::table('announcement_reads')->updateOrInsert(
            ['announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        $route = match ($request->user()->role) {
            'student' => 'student.announcements.index',
            'facilitator' => 'facilitator.announcements.index',
            'coordinator' => 'coordinator.announcements.index',
            'nstp_admin' => 'nstp_admin.announcements.index',
            default => 'admin.announcements.index',
        };

        return redirect()->route($route);
    }

    public function openEvent(Request $request, StudentNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => now()]);

        return redirect()->to($notification->destination($request->user()));
    }

    public function openStudent(Request $request, StudentNotification $notification): RedirectResponse
    {
        return $this->openEvent($request, $notification);
    }

    public function openCategory(Request $request, string $category): RedirectResponse
    {
        $user = $request->user();
        $destination = $this->categoryDestination($user, $category);
        $now = now();

        match ($category) {
            'announcements' => $this->markAnnouncementsRead($user, $now),
            'materials' => StudentNotification::where('user_id', $user->id)
                ->where('type', StudentNotification::MATERIAL)
                ->whereNull('read_at')
                ->update(['read_at' => $now]),
            'assessments' => StudentNotification::where('user_id', $user->id)
                ->where('type', StudentNotification::ASSESSMENT)
                ->whereNull('read_at')
                ->update(['read_at' => $now]),
            'attendance' => StudentNotification::where('user_id', $user->id)
                ->whereIn('type', [StudentNotification::LATE_ATTENDANCE, StudentNotification::ABSENT_ATTENDANCE])
                ->whereNull('read_at')
                ->update(['read_at' => $now]),
            'messages' => DB::table('chat_messages')
                ->where('recipient_id', $user->id)
                ->whereNull('read_at')
                ->update(['read_at' => $now]),
        };

        return redirect()->to($destination);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $now = now();
        $rows = $this->notifications->visibleQuery($request->user())->pluck('id')->map(fn ($id) => [
            'announcement_id' => $id,
            'user_id' => $request->user()->id,
            'read_at' => $now,
        ])->all();
        if ($rows !== []) {
            DB::table('announcement_reads')->upsert($rows, ['announcement_id', 'user_id'], ['read_at']);
        }
        DB::table('chat_messages')
            ->where('recipient_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);
        StudentNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        return back();
    }

    private function markAnnouncementsRead(User $user, mixed $readAt): void
    {
        $rows = $this->notifications->visibleQuery($user)
            ->whereDoesntHave('readers', fn ($readers) => $readers->whereKey($user->id))
            ->pluck('id')
            ->map(fn ($id) => [
                'announcement_id' => $id,
                'user_id' => $user->id,
                'read_at' => $readAt,
            ])->all();

        if ($rows !== []) {
            DB::table('announcement_reads')->upsert($rows, ['announcement_id', 'user_id'], ['read_at']);
        }
    }

    private function categoryDestination(User $user, string $category): string
    {
        if ($category === 'materials' && $user->isCoordinator()) {
            abort(404);
        }

        if ($category === 'messages' && ! $user->isStudent() && ! $user->isFacilitator()) {
            abort(404);
        }

        $prefix = match ($user->role) {
            'super_admin' => 'admin',
            'nstp_admin' => 'nstp_admin',
            default => $user->role,
        };

        return match ($category) {
            'announcements' => route($prefix.'.announcements.index'),
            'materials' => route($prefix.'.materials.index'),
            'assessments' => $user->isCoordinator()
                ? route('coordinator.grades.index')
                : route($prefix.'.assessments.index'),
            'attendance' => route($prefix.'.attendance.index'),
            'messages' => route($prefix.'.messages.index'),
            default => abort(404),
        };
    }
}
