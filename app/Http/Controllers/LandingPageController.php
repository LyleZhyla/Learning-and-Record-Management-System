<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LandingPageController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $authorizedPreview = auth()->check()
            && $request->boolean('preview')
            && auth()->user()->hasPermission('landing.configure');

        if (auth()->check() && ! $authorizedPreview) {
            $routeName = auth()->user()->dashboardRouteName();

            abort_unless($routeName, 403, 'A dashboard is not yet available for this account role.');

            return redirect()->route($routeName);
        }

        return view('welcome', [
            'announcements' => $this->publicAnnouncements(),
            'landing' => SystemSetting::landingPageContent(),
        ]);
    }

    private function publicAnnouncements(): Collection
    {
        try {
            return Announcement::query()
                ->with('component:id,code')
                ->where('status', 'published')
                ->whereIn('audience', ['all', 'students'])
                ->where(function ($query): void {
                    $query->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest('published_at')
                ->limit(3)
                ->get();
        } catch (QueryException) {
            return collect();
        }
    }
}
