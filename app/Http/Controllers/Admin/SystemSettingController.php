<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NstpSection;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(): View
    {
        $setting = SystemSetting::with('updater')->find('inactivity_timeout_minutes');

        return view('admin.settings.edit', [
            'timeoutMinutes' => SystemSetting::inactivityTimeoutMinutes(),
            'setting' => $setting,
            'registrationOpen' => SystemSetting::studentRegistrationIsOpen(),
            'registrationAcademicYear' => SystemSetting::studentRegistrationAcademicYear(),
            'registrationSemester' => SystemSetting::studentRegistrationSemester(),
            'semesters' => NstpSection::SEMESTERS,
            'branding' => SystemSetting::branding(),
            'studentIdDesign' => SystemSetting::studentIdDesign(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inactivity_timeout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'student_registration_open' => ['required', 'boolean'],
            'student_registration_academic_year' => ['required', 'regex:/^(\d{4})-(\d{4})$/', function (string $attribute, mixed $value, \Closure $fail): void {
                [$start, $end] = array_map('intval', explode('-', (string) $value));
                if ($end !== $start + 1) {
                    $fail('The academic year must contain consecutive years, such as 2026-2027.');
                }
            }],
            'student_registration_semester' => ['required', 'in:'.implode(',', array_keys(NstpSection::SEMESTERS))],
            'organization_name' => ['sometimes', 'required', 'string', 'max:150'],
            'organization_short_name' => ['sometimes', 'required', 'string', 'max:20'],
            'program_name' => ['sometimes', 'required', 'string', 'max:150'],
            'program_short_name' => ['sometimes', 'required', 'string', 'max:20'],
            'platform_name' => ['sometimes', 'required', 'string', 'max:180'],
            'organization_website' => ['nullable', 'url:http,https', 'max:255'],
            'organization_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'program_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_organization_logo' => ['nullable', 'boolean'],
            'remove_program_logo' => ['nullable', 'boolean'],
            'student_id_title' => ['sometimes', 'required', 'string', 'max:40'],
            'student_id_primary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'student_id_secondary_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'student_id_accent_color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'student_id_footer_text' => ['sometimes', 'required', 'string', 'max:120'],
            'student_id_show_course' => ['sometimes', 'boolean'],
            'student_id_show_component' => ['sometimes', 'boolean'],
            'student_id_show_emergency_contact' => ['sometimes', 'boolean'],
        ]);

        $branding = SystemSetting::branding();
        foreach (['organization', 'program'] as $type) {
            $pathKey = $type.'_logo_path';
            $removeKey = 'remove_'.$type.'_logo';
            $fileKey = $type.'_logo';

            if ($request->boolean($removeKey)) {
                if (filled($branding[$pathKey])) {
                    Storage::disk('public')->delete($branding[$pathKey]);
                }
                $branding[$pathKey] = null;
            }
            if ($request->hasFile($fileKey)) {
                $oldPath = $branding[$pathKey] ?? null;
                $branding[$pathKey] = $request->file($fileKey)->store('branding/institution', 'public');
                if (filled($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }
        foreach (array_keys(SystemSetting::BRANDING_DEFAULTS) as $key) {
            if (! str_ends_with($key, '_logo_path')) {
                $branding[$key] = $validated[$key] ?? $branding[$key];
            }
        }

        SystemSetting::updateOrCreate(
            ['key' => 'institution_branding'],
            ['value' => json_encode($branding, JSON_UNESCAPED_SLASHES), 'updated_by' => $request->user()->id],
        );

        $studentIdDesign = SystemSetting::studentIdDesign();
        $studentIdFieldMap = [
            'student_id_title' => 'title',
            'student_id_primary_color' => 'primary_color',
            'student_id_secondary_color' => 'secondary_color',
            'student_id_accent_color' => 'accent_color',
            'student_id_footer_text' => 'footer_text',
            'student_id_show_course' => 'show_course',
            'student_id_show_component' => 'show_component',
            'student_id_show_emergency_contact' => 'show_emergency_contact',
        ];
        foreach ($studentIdFieldMap as $requestKey => $designKey) {
            if (array_key_exists($requestKey, $validated)) {
                $studentIdDesign[$designKey] = $validated[$requestKey];
            }
        }
        SystemSetting::updateOrCreate(
            ['key' => 'student_id_design'],
            ['value' => json_encode($studentIdDesign, JSON_UNESCAPED_SLASHES), 'updated_by' => $request->user()->id],
        );

        SystemSetting::updateOrCreate(
            ['key' => 'inactivity_timeout_minutes'],
            ['value' => (string) $validated['inactivity_timeout_minutes'], 'updated_by' => $request->user()->id],
        );

        foreach ([
            'student_registration_open' => $validated['student_registration_open'] ? '1' : '0',
            'student_registration_academic_year' => $validated['student_registration_academic_year'],
            'student_registration_semester' => $validated['student_registration_semester'],
        ] as $key => $value) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'updated_by' => $request->user()->id],
            );
        }

        return back()->with('status', 'System, registration, institutional branding, and student ID design settings were updated.');
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
