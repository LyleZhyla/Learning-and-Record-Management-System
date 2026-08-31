<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
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

        return back();
    }
}
