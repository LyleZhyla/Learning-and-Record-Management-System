<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentForm;
use App\Models\GradingSetting;
use App\Models\NstpSection;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PolicyRequirementController extends Controller
{
    public function index(Request $request): View
    {
        $prefix = $request->user()->isSuperAdmin() ? 'admin' : 'nstp_admin';

        return view('admin.policies.index', [
            'layout' => $prefix === 'admin' ? 'layouts.admin' : 'layouts.nstp-admin',
            'routePrefix' => $prefix,
            'registrationOpen' => SystemSetting::studentRegistrationIsOpen(),
            'registrationAcademicYear' => SystemSetting::studentRegistrationAcademicYear(),
            'registrationSemester' => SystemSetting::studentRegistrationSemester(),
            'componentSelectionOpen' => SystemSetting::componentSelectionIsOpen(),
            'timeoutMinutes' => SystemSetting::inactivityTimeoutMinutes(),
            'defaultPassingPercentage' => SystemSetting::defaultPassingPercentage(),
            'defaultPassingGrade' => SystemSetting::defaultPassingGrade(),
            'requiredDocumentsEnforced' => SystemSetting::requiredDocumentsAreEnforced(),
            'semesters' => NstpSection::SEMESTERS,
            'requiredDocumentCount' => DocumentForm::where('is_active', true)
                ->where('requires_submission', true)->where('is_required', true)->count(),
            'lastUpdatedSetting' => SystemSetting::with('updater')->whereIn('key', [
                'student_registration_open',
                'student_registration_academic_year',
                'student_registration_semester',
                'component_selection_open',
                'inactivity_timeout_minutes',
                'default_passing_percentage',
                'default_passing_grade',
                'required_documents_enforced',
            ])->latest('updated_at')->first(),
        ]);
    }

    public function update(Request $request): RedirectResponse
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
            'component_selection_open' => ['required', 'boolean'],
            'inactivity_timeout_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'default_passing_percentage' => ['required', 'numeric', 'min:1', 'max:99.99'],
            'default_passing_grade' => ['required', 'numeric', 'gt:1', 'lt:5'],
            'required_documents_enforced' => ['required', 'boolean'],
            'apply_grading_policy_to_existing_sections' => ['nullable', 'boolean'],
        ]);

        $values = [
            'student_registration_open' => $validated['student_registration_open'] ? '1' : '0',
            'student_registration_academic_year' => $validated['student_registration_academic_year'],
            'student_registration_semester' => $validated['student_registration_semester'],
            'component_selection_open' => $validated['component_selection_open'] ? '1' : '0',
            'inactivity_timeout_minutes' => (string) $validated['inactivity_timeout_minutes'],
            'default_passing_percentage' => (string) $validated['default_passing_percentage'],
            'default_passing_grade' => (string) $validated['default_passing_grade'],
            'required_documents_enforced' => $validated['required_documents_enforced'] ? '1' : '0',
        ];

        DB::transaction(function () use ($request, $validated, $values): void {
            foreach ($values as $key => $value) {
                SystemSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $request->user()->id],
                );
            }

            if ($validated['apply_grading_policy_to_existing_sections'] ?? false) {
                GradingSetting::query()->update([
                    'passing_percentage' => $validated['default_passing_percentage'],
                    'passing_grade' => $validated['default_passing_grade'],
                    'updated_at' => now(),
                ]);
            }
        });

        return back()->with('status', 'Policies and requirements were updated.');
    }
}
