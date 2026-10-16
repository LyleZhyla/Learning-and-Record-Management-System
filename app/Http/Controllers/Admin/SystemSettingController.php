<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NstpSection;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'branding' => SystemSetting::branding(),
            'systemColors' => SystemSetting::systemColors(),
        ]);
    }

    public function security(): View
    {
        return view('admin.settings.security', [
            'timeoutMinutes' => SystemSetting::inactivityTimeoutMinutes(),
            'securitySetting' => SystemSetting::with('updater')->find('inactivity_timeout_minutes'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $section = $request->validate([
            'settings_section' => ['required', 'in:branding,student_id,registration,security'],
        ])['settings_section'];

        return match ($section) {
            'branding' => $this->updateBranding($request),
            'student_id' => $this->updateStudentIdDesign($request),
            'registration' => $this->updateRegistration($request),
            'security' => $this->updateSecurity($request),
        };
    }

    public function resetDefaults(Request $request): RedirectResponse
    {
        $defaults = [
            'institution_branding' => json_encode(SystemSetting::BRANDING_DEFAULTS, JSON_UNESCAPED_SLASHES),
            'system_colors' => json_encode(SystemSetting::SYSTEM_COLOR_DEFAULTS),
        ];

        DB::transaction(function () use ($defaults, $request): void {
            foreach ($defaults as $key => $value) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $request->user()->id],
                );
            }
        });

        return back()->with('status', 'Branding and system colors were reset to their defaults. Accounts and module data were not changed.');
    }

    private function updateBranding(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_name' => ['required', 'string', 'max:150'],
            'organization_short_name' => ['required', 'string', 'max:20'],
            'program_name' => ['required', 'string', 'max:150'],
            'program_short_name' => ['required', 'string', 'max:20'],
            'platform_name' => ['required', 'string', 'max:180'],
            'organization_website' => ['nullable', 'url:http,https', 'max:255'],
            'system_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'system_secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'system_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $branding = SystemSetting::branding();
        foreach (array_keys(SystemSetting::BRANDING_DEFAULTS) as $key) {
            if (! str_ends_with($key, '_logo_path')) {
                $branding[$key] = $validated[$key] ?? $branding[$key];
            }
        }

        SystemSetting::updateOrCreate(
            ['key' => 'institution_branding'],
            ['value' => json_encode($branding, JSON_UNESCAPED_SLASHES), 'updated_by' => $request->user()->id],
        );
        SystemSetting::updateOrCreate(
            ['key' => 'system_colors'],
            ['value' => json_encode([
                'primary_color' => strtoupper($validated['system_primary_color']),
                'secondary_color' => strtoupper($validated['system_secondary_color']),
                'accent_color' => strtoupper($validated['system_accent_color']),
            ]), 'updated_by' => $request->user()->id],
        );

        return back()->with('status', 'Brand names and system colors were updated. Protected assets were not changed.');
    }

    private function updateStudentIdDesign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id_title' => ['required', 'string', 'max:40'],
            'student_id_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'student_id_secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'student_id_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'student_id_footer_text' => ['required', 'string', 'max:120'],
            'student_id_show_course' => ['required', 'boolean'],
            'student_id_show_component' => ['required', 'boolean'],
            'student_id_show_emergency_contact' => ['required', 'boolean'],
        ]);

        $studentIdDesign = SystemSetting::studentIdDesign();
        foreach ([
            'student_id_title' => 'title',
            'student_id_primary_color' => 'primary_color',
            'student_id_secondary_color' => 'secondary_color',
            'student_id_accent_color' => 'accent_color',
            'student_id_footer_text' => 'footer_text',
            'student_id_show_course' => 'show_course',
            'student_id_show_component' => 'show_component',
            'student_id_show_emergency_contact' => 'show_emergency_contact',
        ] as $requestKey => $designKey) {
            $studentIdDesign[$designKey] = $validated[$requestKey];
        }

        SystemSetting::updateOrCreate(
            ['key' => 'student_id_design'],
            ['value' => json_encode($studentIdDesign, JSON_UNESCAPED_SLASHES), 'updated_by' => $request->user()->id],
        );

        return back()->with('status', 'Student ID design settings were updated.');
    }

    private function updateSecurity(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inactivity_timeout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'inactivity_timeout_minutes'],
            ['value' => (string) $validated['inactivity_timeout_minutes'], 'updated_by' => $request->user()->id],
        );

        return back()->with('status', 'Security timeout was updated.');
    }

    public function updateRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_registration_open' => ['required', 'boolean'],
            'student_registration_academic_year' => ['required', 'regex:/^(\d{4})-(\d{4})$/', function (string $attribute, mixed $value, \Closure $fail): void {
                [$start, $end] = array_map('intval', explode('-', (string) $value));
                if ($end !== $start + 1) {
                    $fail('The academic year must contain consecutive years, such as 2026-2027.');
                }
            }],
            'student_registration_semester' => ['required', 'in:'.implode(',', array_keys(NstpSection::SEMESTERS))],
        ]);

        foreach ([
            'student_registration_open' => $validated['student_registration_open'] ? '1' : '0',
            'student_registration_academic_year' => $validated['student_registration_academic_year'],
            'student_registration_semester' => $validated['student_registration_semester'],
        ] as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $request->user()->id]);
        }

        return back()->with('status', 'Student registration is now '.($validated['student_registration_open'] ? 'open' : 'closed').' for '.NstpSection::SEMESTERS[$validated['student_registration_semester']].' A.Y. '.$validated['student_registration_academic_year'].'.');
    }
}
