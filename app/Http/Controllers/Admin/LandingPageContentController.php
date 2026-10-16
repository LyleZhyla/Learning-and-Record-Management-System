<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Throwable;

class LandingPageContentController extends Controller
{
    public function edit(Request $request): RedirectResponse
    {
        return redirect()->route('landing', ['preview' => 1, 'editor' => 1]);
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

        return redirect()->route('landing', ['preview' => 1, 'editor' => 1])
            ->with('status', 'Landing page changes were published successfully.');
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
