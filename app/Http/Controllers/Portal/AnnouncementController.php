<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $componentIds = $this->componentIds($user);
        $roleAudience = match ($user->role) {
            'student' => 'students',
            'facilitator' => 'facilitators',
            'coordinator' => 'coordinators',
            default => 'all',
        };
        $announcements = Announcement::with(['author', 'component'])
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('audience', ['all', $roleAudience])
            ->where(fn ($query) => $query->whereNull('component_id')->orWhereIn('component_id', $componentIds))
            ->latest('published_at')->paginate(12);

        return view('portal.announcements.index', [
            'announcements' => $announcements,
            'layout' => $this->access->layout($user),
        ]);
    }

    /** @return array<int, int> */
    private function componentIds(User $user): array
    {
        return match ($user->role) {
            'student' => $user->nstpEnrollments()->pluck('component_id')->unique()->values()->all(),
            'facilitator' => $user->facilitatedSections()->pluck('component_id')->unique()->values()->all(),
            'coordinator' => array_values(array_filter([$user->nstp_component_id])),
            default => [],
        };
    }
}
