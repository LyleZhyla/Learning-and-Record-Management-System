<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\LandingPageMedia;
use App\Models\NstpComponent;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LandingPageController extends Controller
{
    public function __invoke(Request $request): View
    {
        $authorizedPreview = auth()->check()
            && $request->boolean('preview')
            && auth()->user()->hasPermission('landing.configure');

        return view('welcome', [
            'announcements' => $this->publicAnnouncements(),
            'landing' => SystemSetting::landingPageContent(),
            'branding' => SystemSetting::branding(),
            'components' => $this->components(),
            'landingMedia' => $this->landingMedia(),
            'landingEditorAuthorized' => $authorizedPreview,
            'landingEditorMode' => $authorizedPreview && $request->boolean('editor'),
            'landingEditorRoutePrefix' => $authorizedPreview ? $this->routePrefix($request) : null,
            'landingSetting' => $authorizedPreview
                ? SystemSetting::with('updater')->find('landing_page_content')
                : null,
        ]);
    }

    private function routePrefix(Request $request): string
    {
        return match ($request->user()->role) {
            'super_admin' => 'admin',
            'nstp_admin' => 'nstp_admin',
            'coordinator' => 'coordinator',
            default => abort(403),
        };
    }

    private function publicAnnouncements(): Collection
    {
        try {
            return Announcement::query()
                ->with('component:id,code')
                ->where('status', 'published')
                ->where('is_public', true)
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

    private function components(): Collection
    {
        try {
            return NstpComponent::query()->whereIn('code', ['CWTS', 'LTS', 'ROTC'])->where('is_active', true)->get()->keyBy('code');
        } catch (QueryException) {
            return collect();
        }
    }

    private function landingMedia(): Collection
    {
        try {
            $activeComponents = NstpComponent::query()->where('is_active', true)
                ->pluck('code')->map(fn (string $code): string => strtolower($code));

            return LandingPageMedia::query()
                ->when($activeComponents->isNotEmpty(), fn ($query) => $query->whereIn('component', $activeComponents))
                ->latest()->get();
        } catch (QueryException) {
            return collect();
        }
    }
}
