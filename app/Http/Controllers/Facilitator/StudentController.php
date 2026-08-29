<?php

namespace App\Http\Controllers\Facilitator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'section_id' => ['nullable', 'integer', 'exists:nstp_sections,id'],
        ]);
        $sectionIds = $request->user()->facilitatedSections()->pluck('id');
        $sections = $request->user()->facilitatedSections()->with('component')->orderBy('code')->get();

        $students = $this->assignedStudents($sectionIds->all())
            ->with(['nstpEnrollments' => fn ($query) => $query
                ->whereIn('section_id', $sectionIds)
                ->with(['component', 'section'])
                ->latest('academic_year')->latest('semester')])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($filters['section_id'] ?? null, fn ($query, $sectionId) => $query
                ->whereHas('nstpEnrollments', fn ($enrollments) => $enrollments
                    ->where('section_id', $sectionId)
                    ->whereIn('section_id', $sectionIds)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('facilitator.students.index', compact('students', 'sections', 'filters'));
    }

    public function show(Request $request, User $student): View
    {
        $sectionIds = $request->user()->facilitatedSections()->pluck('id');
        abort_unless($this->assignedStudents($sectionIds->all())->whereKey($student)->exists(), 404);

        $student->load([
            'nstpEnrollments' => fn ($query) => $query->whereIn('section_id', $sectionIds)->with(['component', 'section']),
            'attendanceRecords' => fn ($query) => $query
                ->whereHas('attendanceSession', fn ($sessions) => $sessions->whereIn('section_id', $sectionIds))
                ->with('attendanceSession.section.component')->latest('checked_in_at'),
            'assessmentSubmissions' => fn ($query) => $query
                ->whereHas('assessment', fn ($assessments) => $assessments->whereIn('section_id', $sectionIds))
                ->with('assessment.section.component')->latest('submitted_at'),
        ]);

        return view('facilitator.students.show', compact('student'));
    }

    /** @param array<int, int> $sectionIds */
    private function assignedStudents(array $sectionIds): Builder
    {
        return User::query()
            ->where('role', 'student')
            ->whereHas('nstpEnrollments', fn ($query) => $query->whereIn('section_id', $sectionIds));
    }
}
