<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\NstpEnrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComponentController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nstp_component_id' => [
                'required',
                'integer',
                Rule::exists('nstp_components', 'id')->where('is_active', true),
            ],
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
            'status' => 'enrolled',
        ])->save();

        return back()->with('status', 'Your NSTP component selection was updated successfully.');
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
