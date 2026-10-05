<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\NstpSection;
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
            'registrationOpen' => SystemSetting::studentRegistrationIsOpen(),
            'registrationAcademicYear' => SystemSetting::studentRegistrationAcademicYear(),
            'registrationSemester' => SystemSetting::studentRegistrationSemester(),
            'semesters' => NstpSection::SEMESTERS,
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
        ]);

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

        return back()->with('status', 'System and student registration settings were updated.');
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
