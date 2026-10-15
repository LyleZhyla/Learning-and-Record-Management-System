<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingPageContentController extends Controller
{
    public function edit(Request $request): RedirectResponse
    {
        return redirect()->route('landing', ['preview' => 1]);
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

        return redirect()->route('landing', ['preview' => 1, 'editor' => 1])
            ->with('status', 'Landing page changes were published successfully.');
    }

    private function rules(): array
    {
        $rules = [];

        foreach (array_keys(SystemSetting::LANDING_PAGE_DEFAULTS) as $key) {
            if (str_starts_with($key, 'hero_slide_')) {
                $rules[$key] = ['required', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                    $isLocalHero = is_string($value)
                        && preg_match('#^/images/landing/[A-Za-z0-9._-]+\.(?:png|jpe?g|webp)$#i', $value) === 1;
                    $isRemoteImage = filter_var($value, FILTER_VALIDATE_URL)
                        && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);

                    if (! $isLocalHero && ! $isRemoteImage) {
                        $fail('The slideshow image must be a valid HTTP(S) URL or a landing image path.');
                    }
                }];

                continue;
            }

            $rules[$key] = str_ends_with($key, '_url')
                ? ['required', 'url:http,https', 'max:2048']
                : ['required', 'string', 'max:'.(str_ends_with($key, '_body') || str_ends_with($key, '_answer') || str_starts_with($key, 'hero_lead_') || str_ends_with($key, '_intro') || $key === 'about_lead' ? 2000 : 180)];
        }

        return $rules;
    }
}
