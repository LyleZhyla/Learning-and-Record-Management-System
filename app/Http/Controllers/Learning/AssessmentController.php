<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Services\GradeService;
use App\Services\PortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(private PortalAccessService $access, private GradeService $grades) {}

    public function index(Request $request): View
    {
        $sectionIds = $this->access->manageableSections($request->user())->pluck('id');
        $assessments = Assessment::with(['section.component', 'creator'])->withCount('submissions')
            ->whereIn('section_id', $sectionIds)->latest()->paginate(15);

        return view('learning.assessments.index', $this->context($request) + compact('assessments'));
    }

    public function create(Request $request): View
    {
        return view('learning.assessments.create', $this->context($request) + [
            'sections' => $this->access->manageableSections($request->user())->with('component')->where('status', 'active')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'exists:nstp_sections,id'],
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['quiz', 'activity', 'project', 'exam'])],
            'instructions' => ['nullable', 'string'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:10000'],
            'weight' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $section = NstpSection::findOrFail($validated['section_id']);
        $this->access->ensureCanManageSection($request->user(), $section);
        $assessment = Assessment::create([...$validated, 'created_by' => $request->user()->id, 'published_at' => $validated['status'] === 'published' ? now() : null]);

        return redirect()->route($this->access->routePrefix($request->user()).'.assessments.show', $assessment)
            ->with('status', 'Assessment created successfully.');
    }

    public function show(Request $request, Assessment $assessment): View
    {
        $assessment->load(['section.component', 'submissions.student', 'submissions.grader']);
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        $students = NstpEnrollment::with('student')->where('section_id', $assessment->section_id)->get()->sortBy(fn ($item) => $item->student->name);

        return view('learning.assessments.show', $this->context($request) + compact('assessment', 'students'));
    }

    public function grade(Request $request, Assessment $assessment, AssessmentSubmission $submission): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        abort_unless($submission->assessment_id === $assessment->id, 404);
        $validated = $request->validate(['score' => ['required', 'numeric', 'min:0', 'max:'.$assessment->max_score], 'feedback' => ['nullable', 'string', 'max:3000']]);
        $submission->update([...$validated, 'graded_by' => $request->user()->id, 'graded_at' => now()]);

        return back()->with('status', 'Submission graded successfully.');
    }

    public function grades(Request $request): View
    {
        $sections = $this->access->manageableSections($request->user())->with(['component', 'enrollments.student'])->orderBy('code')->get();
        $section = $sections->firstWhere('id', $request->integer('section')) ?? $sections->first();
        $summaries = $section ? $section->enrollments->map(fn ($enrollment) => ['student' => $enrollment->student] + $this->grades->summary($enrollment->student, $section->id)) : collect();

        return view('learning.grades.index', $this->context($request) + compact('sections', 'section', 'summaries'));
    }

    private function context(Request $request): array
    {
        return ['layout' => $this->access->layout($request->user()), 'routePrefix' => $this->access->routePrefix($request->user())];
    }
}
