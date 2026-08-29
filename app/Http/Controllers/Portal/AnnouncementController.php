<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\PortalAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
