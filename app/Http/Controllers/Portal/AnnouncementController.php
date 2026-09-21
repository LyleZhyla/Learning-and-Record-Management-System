<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\NotificationService;
use App\Services\PortalAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementController extends Controller
{
    public function __construct(private PortalAccessService $access, private NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $announcements = $this->notifications->visibleQuery($user)
            ->with(['author', 'component'])
            ->latest('published_at')->paginate(12);

        return view('portal.announcements.index', [
            'announcements' => $announcements,
            'layout' => $this->access->layout($user),
        ]);
    }

    public function downloadAttachment(Request $request, Announcement $announcement): StreamedResponse
    {
        $user = $request->user();
        $canManage = $user->isSuperAdmin() || $announcement->author_id === $user->id;
        $canView = $this->notifications->visibleQuery($user)->whereKey($announcement->id)->exists();
        abort_unless($canManage || $canView, 403);
        abort_unless($announcement->attachment_path && Storage::disk('local')->exists($announcement->attachment_path), 404);

        return Storage::disk('local')->download(
            $announcement->attachment_path,
            $announcement->attachment_original_name ?? 'announcement-attachment',
        );
    }
}
