<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPageMedia;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class LandingPageContentController extends Controller
{
    public function edit(Request $request): View
    {
        return view('landing-page.edit', [
            'layout' => 'layouts.admin',
            'routePrefix' => 'admin',
            'mediaOnly' => false,
            'allowedComponent' => null,
            'mediaStoreRoute' => 'admin.landing-page.media.store',
            'mediaDestroyRoute' => 'admin.landing-page.media.destroy',
            'landing' => SystemSetting::landingPageContent(),
            'setting' => SystemSetting::with('updater')->find('landing_page_content'),
            'landingMedia' => LandingPageMedia::with('creator')->latest()->get(),
        ]);
    }

    public function media(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isNstpAdmin() || $user->isCoordinator(), 403);

        $prefix = $user->isCoordinator() ? 'coordinator' : 'nstp_admin';
        $component = $user->isCoordinator() ? strtolower((string) $user->nstpComponent?->code) : null;
        abort_if($user->isCoordinator() && ! in_array($component, ['rotc', 'cwts', 'lts'], true), 403, 'A component assignment is required to manage landing-page media.');

        return view('landing-page.edit', [
            'layout' => $prefix === 'coordinator' ? 'layouts.coordinator' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'mediaOnly' => true,
            'allowedComponent' => $component,
            'mediaStoreRoute' => $prefix.'.landing-media.store',
            'mediaDestroyRoute' => $prefix.'.landing-media.destroy',
            'landing' => SystemSetting::landingPageContent(),
            'setting' => null,
            'landingMedia' => LandingPageMedia::with('creator')
                ->when($component, fn ($query) => $query->where('component', $component))
                ->latest()
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $currentContent = SystemSetting::landingPageContent();
        $content = array_replace(
            $currentContent,
            Arr::only($validated, array_keys(SystemSetting::LANDING_PAGE_DEFAULTS)),
        );
        $newUploads = [];
        $replacedUploads = [];

        for ($slide = 1; $slide <= 4; $slide++) {
            $uploadKey = "hero_slide_{$slide}_image";
            $contentKey = "hero_slide_{$slide}_url";

            if (! $request->hasFile($uploadKey)) {
                continue;
            }

            $storedPath = $request->file($uploadKey)->store('landing-page/hero', 'public');
            $newUploads[] = $storedPath;

            if ($oldPath = $this->managedHeroPath($currentContent[$contentKey] ?? null)) {
                $replacedUploads[] = $oldPath;
            }

            $content[$contentKey] = '/storage/'.$storedPath;
        }

        try {
            SystemSetting::updateOrCreate(
                ['key' => 'landing_page_content'],
                [
                    'value' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_by' => $request->user()->id,
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newUploads);
            throw $exception;
        }

        $activeUploads = array_values(array_filter(array_map(
            fn (mixed $url): ?string => $this->managedHeroPath($url),
            Arr::only($content, ['hero_slide_1_url', 'hero_slide_2_url', 'hero_slide_3_url', 'hero_slide_4_url']),
        )));
        Storage::disk('public')->delete(array_diff($replacedUploads, $newUploads, $activeUploads));

        $redirect = $request->input('editor_context') === 'full'
            ? redirect()->route('admin.landing-page.edit')
            : redirect()->route('landing', ['preview' => 1, 'editor' => 1]);

        return $redirect
            ->with('status', 'Landing page changes were published successfully.');
    }

    public function storeMedia(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'media_type' => ['required', 'in:photo,video'],
            'media_component' => ['required', 'in:rotc,cwts,lts'],
            'media_title' => ['required', 'string', 'max:180'],
            'media_description' => ['nullable', 'string', 'max:1000'],
            'media_file' => $request->input('media_type') === 'video'
                ? ['required', 'file', 'mimes:mp4,webm,mov', 'max:102400']
                : ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $type = $validated['media_type'];
        $component = $request->user()->isCoordinator()
            ? strtolower((string) $request->user()->nstpComponent?->code)
            : $validated['media_component'];
        abort_if($request->user()->isCoordinator() && ! in_array($component, ['rotc', 'cwts', 'lts'], true), 403, 'A component assignment is required to upload landing-page media.');
        $path = $request->file('media_file')->store('landing-page/media/'.$type, 'public');

        try {
            LandingPageMedia::create([
                'type' => $type,
                'component' => $component,
                'title' => $validated['media_title'],
                'description' => $validated['media_description'] ?? null,
                'file_path' => $path,
                'created_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return back()->with(
            'status',
            ucfirst($type).' uploaded to Public Landing Page → Activities → '.strtoupper($component).'.',
        );
    }

    public function destroyMedia(Request $request, LandingPageMedia $landingPageMedia): RedirectResponse
    {
        if ($request->user()->isCoordinator()) {
            $component = strtolower((string) $request->user()->nstpComponent?->code);
            abort_unless($component && $landingPageMedia->component === $component, 403);
        }

        $path = $landingPageMedia->file_path;
        $landingPageMedia->delete();
        Storage::disk('public')->delete($path);

        return back()->with('status', 'Landing page media was removed.');
    }

    private function rules(): array
    {
        $rules = [];

        foreach (array_keys(SystemSetting::LANDING_PAGE_DEFAULTS) as $key) {
            if (str_starts_with($key, 'hero_slide_')) {
                $slide = (int) preg_replace('/\D+/', '', $key);
                $rules[$key] = ['nullable', 'required_without:hero_slide_'.$slide.'_image', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                    $isLocalHero = is_string($value)
                        && preg_match('#^/(?:images/landing/[A-Za-z0-9._-]+|storage/landing-page/hero/[A-Za-z0-9._-]+)\.(?:png|jpe?g|webp)$#i', $value) === 1;
                    $isRemoteImage = filter_var($value, FILTER_VALIDATE_URL)
                        && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);

                    if (! $isLocalHero && ! $isRemoteImage) {
                        $fail('The slideshow image must be a valid HTTP(S) URL or a landing image path.');
                    }
                }];
                $rules['hero_slide_'.$slide.'_image'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

                continue;
            }

            $rules[$key] = str_ends_with($key, '_url')
                ? ['required', 'url:http,https', 'max:2048']
                : ['required', 'string', 'max:'.(str_ends_with($key, '_body') || str_ends_with($key, '_answer') || str_starts_with($key, 'hero_lead_') || str_ends_with($key, '_intro') || $key === 'about_lead' ? 2000 : 180)];
        }

        return $rules;
    }

    private function managedHeroPath(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && preg_match('#^/storage/(landing-page/hero/[A-Za-z0-9._-]+\.(?:png|jpe?g|webp))$#i', $path, $matches) === 1
            ? $matches[1]
            : null;
    }
}
