<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        $setting = SystemSetting::with('updater')->find('inactivity_timeout_minutes');

        return view('admin.settings.edit', [
            'timeoutMinutes' => SystemSetting::inactivityTimeoutMinutes(),
            'setting' => $setting,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inactivity_timeout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'inactivity_timeout_minutes'],
            ['value' => (string) $validated['inactivity_timeout_minutes'], 'updated_by' => $request->user()->id],
        );

        return back()->with('status', "Inactivity timeout updated to {$validated['inactivity_timeout_minutes']} minutes for all accounts.");
    }
}
