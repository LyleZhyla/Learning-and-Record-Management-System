<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\LearningMaterial;
use App\Services\GradeService;
use App\Services\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearningController extends Controller
{
    public function __construct(private PortalAccessService $access, private GradeService $grades) {}

    public function materials(Request $request): View
    {
        $enrollment = $this->access->currentEnrollment($request->user());
        $materials = LearningMaterial::with(['component', 'section', 'creator'])->whereRaw('1 = 0')->paginate();
        if ($enrollment) {
            $materials = LearningMaterial::with(['component', 'section', 'creator'])->where('status', 'published')
                ->where('component_id', $enrollment->component_id)
                ->where(fn ($q) => $q->whereNull('section_id')->orWhere('section_id', $enrollment->section_id))
                ->latest('published_at')->paginate(15);
        }
        return view('student.materials.index', compact('enrollment', 'materials'));
    }

    public function assessments(Request $request): View
    {
        $enrollment = $this->access->currentEnrollment($request->user());
        $assessments = Assessment::with(['section.component', 'submissions' => fn ($q) => $q->where('student_id', $request->user()->id)])->whereRaw('1 = 0')->paginate();
        if ($enrollment) {
            $assessments = Assessment::with(['section.component', 'submissions' => fn ($q) => $q->where('student_id', $request->user()->id)])
                ->where('section_id', $enrollment->section_id)->where('status', 'published')->latest()->paginate(15);
        }
        return view('student.assessments.index', compact('enrollment', 'assessments'));
    }

    public function show(Request $request, Assessment $assessment): View
    {
        $enrollment = $this->access->currentEnrollment($request->user());
        abort_unless($enrollment && $assessment->section_id === $enrollment->section_id && $assessment->status === 'published', 403);
        $submission = $assessment->submissions()->where('student_id', $request->user()->id)->first();
        return view('student.assessments.show', compact('assessment', 'submission', 'enrollment'));
    }

    public function submit(Request $request, Assessment $assessment): RedirectResponse
    {
        $enrollment = $this->access->currentEnrollment($request->user());
        abort_unless($enrollment && $assessment->section_id === $enrollment->section_id && $assessment->status === 'published', 403);
        $validated = $request->validate([
            'answer_text' => ['nullable', 'string', 'max:20000', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,jpg,jpeg,png', 'max:10240', 'required_without:answer_text'],
        ]);
        $file = $request->file('file');
        $existingSubmission = AssessmentSubmission::where('assessment_id', $assessment->id)
            ->where('student_id', $request->user()->id)
            ->first();
        AssessmentSubmission::updateOrCreate(
            ['assessment_id' => $assessment->id, 'student_id' => $request->user()->id],
            [
                'answer_text' => $validated['answer_text'] ?? null,
                'file_path' => $file?->store('assessment-submissions') ?? $existingSubmission?->file_path,
                'original_filename' => $file?->getClientOriginalName() ?? $existingSubmission?->original_filename,
                'submitted_at' => now(), 'score' => null, 'feedback' => null, 'graded_by' => null, 'graded_at' => null,
            ],
        );
        return back()->with('status', 'Your work was submitted successfully.');
    }

    public function grades(Request $request): View
    {
        $enrollment = $this->access->currentEnrollment($request->user());
        $summary = $enrollment?->section_id ? $this->grades->summary($request->user(), $enrollment->section_id) : null;
        return view('student.grades.index', compact('enrollment', 'summary'));
    }
}
