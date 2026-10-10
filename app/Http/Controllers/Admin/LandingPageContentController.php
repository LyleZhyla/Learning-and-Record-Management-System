<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingPageContentController extends Controller
{
    public function edit(Request $request): View
    {
        $setting = SystemSetting::with('updater')->find('landing_page_content');

        return view('landing-page.edit', [
            'landing' => SystemSetting::landingPageContent(),
            'setting' => $setting,
            'routePrefix' => $this->routePrefix($request),
            'layout' => $this->layout($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $content = array_replace(SystemSetting::LANDING_PAGE_DEFAULTS, $validated);

        SystemSetting::updateOrCreate(
            ['key' => 'landing_page_content'],
            [
                'value' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_by' => $request->user()->id,
            ],
        );

        return back()->with('status', 'Landing page content was published successfully.');
    }

    private function rules(): array
    {
        $rules = [];

        foreach (array_keys(SystemSetting::LANDING_PAGE_DEFAULTS) as $key) {
            $rules[$key] = str_ends_with($key, '_url')
                ? ['required', 'url:http,https', 'max:2048']
                : ['required', 'string', 'max:'.(str_ends_with($key, '_body') || str_ends_with($key, '_answer') || str_starts_with($key, 'hero_lead_') || str_ends_with($key, '_intro') || $key === 'about_lead' ? 2000 : 180)];
        }

        return $rules;
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

    private function layout(Request $request): string
    {
        return match ($request->user()->role) {
            'super_admin' => 'layouts.admin',
            'nstp_admin' => 'layouts.nstp-admin',
            'coordinator' => 'layouts.coordinator',
            default => abort(403),
        };
    }
}
