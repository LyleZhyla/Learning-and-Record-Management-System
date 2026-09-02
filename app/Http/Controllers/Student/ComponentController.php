<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ComponentController extends Controller
{
    public function edit(Request $request): View
    {
        $academicYear = $this->currentAcademicYear();
        $semester = $this->currentSemester();
        $currentEnrollment = NstpEnrollment::query()
            ->where('student_id', $request->user()->id)
            ->where('academic_year', $academicYear)
            ->where('semester', $semester)
            ->with(['component', 'section'])
            ->first();

        return view('student.component', [
            'availableComponents' => NstpComponent::query()
                ->when(
                    $currentEnrollment,
                    fn ($query) => $query->whereKey($currentEnrollment->component_id),
                    fn ($query) => $query->where('is_active', true)
                )
                ->orderBy('code')
                ->get(),
            'currentEnrollment' => $currentEnrollment,
            'academicYear' => $academicYear,
            'semesterLabel' => NstpSection::SEMESTERS[$semester],
            'shirtSizes' => NstpEnrollment::SHIRT_SIZES,
            'rotcCategories' => NstpEnrollment::ROTC_CATEGORIES,
            'componentSelectionOpen' => SystemSetting::componentSelectionIsOpen(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $academicYear = $this->currentAcademicYear();
        $semester = $this->currentSemester();
        $enrollment = NstpEnrollment::firstOrNew([
            'student_id' => $request->user()->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
        ]);

        if ($enrollment->exists) {
            return back()->withErrors([
                'selection' => 'Your NSTP component, ROTC details, and shirt size are final and can no longer be changed.',
            ]);
        }

        if (! SystemSetting::componentSelectionIsOpen()) {
            return back()->withErrors([
                'selection' => 'NSTP component selection is currently closed. Please wait for an administrator to reopen it.',
            ]);
        }

        $selectedComponent = NstpComponent::query()
            ->find($request->input('nstp_component_id'));
        $isAdvancedRotc = $selectedComponent?->code === 'ROTC'
            && in_array($request->input('rotc_category'), ['MS-31', 'MS-41'], true);

        $validated = $request->validate([
            'nstp_component_id' => [
                'required',
                'integer',
                Rule::exists('nstp_components', 'id')->where('is_active', true),
            ],
            'shirt_size' => ['required', Rule::in(array_keys(NstpEnrollment::SHIRT_SIZES))],
            'rotc_category' => [
                Rule::requiredIf(fn () => $selectedComponent?->code === 'ROTC'),
                'nullable',
                Rule::in(array_keys(NstpEnrollment::ROTC_CATEGORIES)),
            ],
            'ms1_proof' => [
                Rule::requiredIf(fn () => $isAdvancedRotc && blank($enrollment->rotc_proof_path)),
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:5120',
            ],
        ], [
            'nstp_component_id.exists' => 'The selected NSTP component is not available.',
        ]);

        $componentId = (int) $validated['nstp_component_id'];
        $rotcCategory = $selectedComponent?->code === 'ROTC' ? $validated['rotc_category'] : null;
        $oldProofPath = $enrollment->rotc_proof_path;
        $newProof = $request->file('ms1_proof');
        $newProofPath = $newProof?->store('rotc-ms1-proofs');
        $approvalMustReset = ! $enrollment->exists
            || $enrollment->component_id !== $componentId
            || $enrollment->rotc_category !== $rotcCategory
            || $newProof !== null;

        try {
            $enrollment->fill([
                'component_id' => $componentId,
                'shirt_size' => $validated['shirt_size'],
                'rotc_category' => $rotcCategory,
                'rotc_proof_path' => $isAdvancedRotc ? ($newProofPath ?? $oldProofPath) : null,
                'rotc_proof_original_name' => $isAdvancedRotc ? ($newProof?->getClientOriginalName() ?? $enrollment->rotc_proof_original_name) : null,
                'rotc_approval_status' => $isAdvancedRotc
                    ? ($approvalMustReset ? 'pending' : $enrollment->rotc_approval_status)
                    : null,
                'rotc_approved_by' => $isAdvancedRotc && ! $approvalMustReset ? $enrollment->rotc_approved_by : null,
                'rotc_approved_at' => $isAdvancedRotc && ! $approvalMustReset ? $enrollment->rotc_approved_at : null,
                'status' => $isAdvancedRotc && ($approvalMustReset || $enrollment->rotc_approval_status !== 'approved')
                    ? 'pending_approval'
                    : 'enrolled',
            ])->save();
        } catch (Throwable $exception) {
            if ($newProofPath) {
                Storage::disk('local')->delete($newProofPath);
            }

            throw $exception;
        }

        if ($oldProofPath && ($newProofPath || ! $isAdvancedRotc)) {
            Storage::disk('local')->delete($oldProofPath);
        }

        $message = $isAdvancedRotc
            ? 'Your ROTC request was submitted and is waiting for coordinator approval.'
            : 'Your NSTP enrollment preferences were updated successfully.';

        return back()->with('status', $message);
    }

    private function currentAcademicYear(): string
    {
        $year = now()->year;
        $start = now()->month >= 6 ? $year : $year - 1;

        return $start.'-'.($start + 1);
    }

    private function currentSemester(): string
    {
        return now()->month >= 6 ? 'first' : 'second';
    }
}
