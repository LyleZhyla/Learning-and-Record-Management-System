<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\NstpComponent;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComponentController extends Controller
{
    public function edit(Request $request): View
    {
        $academicYear = $this->currentAcademicYear();
        $semester = $this->currentSemester();

        return view('student.component', [
            'availableComponents' => NstpComponent::query()->where('is_active', true)->orderBy('code')->get(),
            'currentEnrollment' => NstpEnrollment::query()
                ->where('student_id', $request->user()->id)
                ->where('academic_year', $academicYear)
                ->where('semester', $semester)
                ->with(['component', 'section'])
                ->first(),
            'academicYear' => $academicYear,
            'semesterLabel' => NstpSection::SEMESTERS[$semester],
            'shirtSizes' => NstpEnrollment::SHIRT_SIZES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nstp_component_id' => [
                'required',
                'integer',
                Rule::exists('nstp_components', 'id')->where('is_active', true),
            ],
            'shirt_size' => ['required', Rule::in(array_keys(NstpEnrollment::SHIRT_SIZES))],
        ]);

        $componentId = (int) $validated['nstp_component_id'];
        $academicYear = $this->currentAcademicYear();
        $semester = $this->currentSemester();
        $enrollment = NstpEnrollment::firstOrNew([
            'student_id' => $request->user()->id,
            'academic_year' => $academicYear,
            'semester' => $semester,
        ]);

        if ($enrollment->exists && $enrollment->component_id !== $componentId) {
            $enrollment->section_id = null;
        }

        $enrollment->fill([
            'component_id' => $componentId,
            'shirt_size' => $validated['shirt_size'],
            'status' => 'enrolled',
        ])->save();

        return back()->with('status', 'Your NSTP component and shirt size were updated successfully.');
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
