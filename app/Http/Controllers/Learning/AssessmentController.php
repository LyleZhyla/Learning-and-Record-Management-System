<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\GradingCategory;
use App\Models\GradingSetting;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Services\GradeService;
use App\Services\PortalAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AssessmentController extends Controller
{
    public function __construct(private PortalAccessService $access, private GradeService $grades) {}

    public function index(Request $request): View
    {
        $sectionIds = $this->access->manageableSections($request->user())->pluck('id');
        $assessments = Assessment::with(['section.component', 'creator', 'gradingCategory'])->withCount('submissions')
            ->whereIn('section_id', $sectionIds)->latest()->paginate(15);

        return view('learning.assessments.index', $this->context($request) + compact('assessments'));
    }

    public function create(Request $request): View
    {
        $sections = $this->access->manageableSections($request->user())->with('component')->where('status', 'active')->orderBy('code')->get();
        $sections->each(fn ($section) => $this->ensureGradingStructure($section));
        $sections->load('gradingCategories');

        return view('learning.assessments.create', $this->context($request) + [
            'sections' => $sections,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'section_id' => ['required', 'exists:nstp_sections,id'],
            'grading_category_id' => ['nullable', 'integer', 'exists:grading_categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['quiz', 'activity', 'project', 'exam'])],
            'instructions' => ['nullable', 'string'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:10000'],
            'weight' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ]);
        $section = NstpSection::findOrFail($validated['section_id']);
        $this->access->ensureCanManageSection($request->user(), $section);
        $this->ensureGradingStructure($section);
        $category = isset($validated['grading_category_id'])
            ? GradingCategory::where('section_id', $section->id)->findOrFail($validated['grading_category_id'])
            : $section->gradingCategories()->where('name', match ($validated['type']) {
                'quiz' => 'Quizzes', 'exam' => 'Term Test', 'project' => 'Requirements', default => 'Class Standing'
            })->first();
        $validated['grading_category_id'] = $category?->id;
        $validated['weight'] = $category?->weight ?? ($validated['weight'] ?? 10);
        $assessment = Assessment::create([...$validated, 'created_by' => $request->user()->id, 'published_at' => $validated['status'] === 'published' ? now() : null]);

        return redirect()->route($this->access->routePrefix($request->user()).'.assessments.show', $assessment)
            ->with('status', 'Assessment created successfully.');
    }

    public function show(Request $request, Assessment $assessment): View
    {
        $assessment->load(['section.component', 'gradingCategory', 'submissions.student', 'submissions.grader']);
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
        $summaries = collect();
        $categories = collect();
        $settings = null;

        if ($section) {
            $this->ensureGradingStructure($section);
            $section->load(['gradingCategories.assessments.submissions', 'gradingSetting']);
            $categories = $section->gradingCategories;
            $settings = $section->gradingSetting;
            $summaries = $section->enrollments->sortBy(fn ($enrollment) => $enrollment->student->name)->map(
                fn ($enrollment) => ['student' => $enrollment->student] + $this->grades->summary($enrollment->student, $section->id),
            )->values();
        }

        return view('learning.grades.index', $this->context($request) + compact('sections', 'section', 'summaries', 'categories', 'settings'));
    }

    public function updateGradeStructure(Request $request, NstpSection $section): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $section);
        $this->access->ensureCanConfigureGrades($request->user());
        $this->ensureGradingStructure($section);
        $validated = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.name' => ['required', 'string', 'max:80'],
            'categories.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'categories.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'new_category.name' => ['nullable', 'string', 'max:80'],
            'new_category.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'new_category.color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'passing_percentage' => ['required', 'numeric', 'min:1', 'max:99.99'],
            'highest_grade' => ['required', 'numeric', 'min:0', 'max:5'],
            'passing_grade' => ['required', 'numeric', 'min:0', 'max:5'],
            'failing_grade' => ['required', 'numeric', 'min:0', 'max:5'],
        ]);

        $existingIds = $section->gradingCategories()->pluck('id')->map(fn ($id) => (string) $id);
        $submittedIds = collect(array_keys($validated['categories']));
        if ($submittedIds->diff($existingIds)->isNotEmpty()) {
            abort(403);
        }

        $newCategory = $validated['new_category'] ?? [];
        $hasNewCategory = filled($newCategory['name'] ?? null);
        $totalWeight = collect($validated['categories'])->sum(fn ($category) => (float) $category['weight'])
            + ($hasNewCategory ? (float) ($newCategory['weight'] ?? 0) : 0);

        if (abs($totalWeight - 100) > 0.001) {
            throw ValidationException::withMessages(['categories' => 'The total category weight must be exactly 100%. Current total: '.number_format($totalWeight, 2).'%.']);
        }
        if ((float) $validated['highest_grade'] >= (float) $validated['passing_grade'] || (float) $validated['passing_grade'] >= (float) $validated['failing_grade']) {
            throw ValidationException::withMessages(['passing_grade' => 'Use an ascending scale such as 1.00 highest, 3.00 passing, and 5.00 failing.']);
        }

        DB::transaction(function () use ($section, $validated, $hasNewCategory, $newCategory) {
            foreach ($validated['categories'] as $id => $category) {
                GradingCategory::where('section_id', $section->id)->findOrFail($id)->update($category);
            }
            if ($hasNewCategory) {
                $section->gradingCategories()->create([
                    'name' => $newCategory['name'],
                    'weight' => $newCategory['weight'] ?? 0,
                    'color' => $newCategory['color'] ?? '#64748b',
                    'sort_order' => $section->gradingCategories()->max('sort_order') + 1,
                ]);
            }
            GradingSetting::updateOrCreate(['section_id' => $section->id], [
                'passing_percentage' => $validated['passing_percentage'],
                'highest_grade' => $validated['highest_grade'],
                'passing_grade' => $validated['passing_grade'],
                'failing_grade' => $validated['failing_grade'],
            ]);
        });

        return back()->with('status', 'Grading categories and grade scale updated.');
    }

    public function destroyGradeCategory(Request $request, GradingCategory $category): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $category->section);
        $this->access->ensureCanConfigureGrades($request->user());
        if ($category->assessments()->exists()) {
            return back()->withErrors(['category' => 'Move or delete the score items in this category first.']);
        }
        if ($category->section->gradingCategories()->count() <= 1) {
            return back()->withErrors(['category' => 'A grading sheet needs at least one category.']);
        }
        $category->delete();

        return back()->with('status', 'Category deleted. Adjust the remaining weights to total 100%.');
    }

    public function storeGradeItem(Request $request, NstpSection $section): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $section);
        $this->access->ensureCanConfigureGrades($request->user());
        $validated = $request->validate([
            'grading_category_id' => ['required', 'integer', 'exists:grading_categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'max_score' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ]);
        $category = GradingCategory::where('section_id', $section->id)->findOrFail($validated['grading_category_id']);
        Assessment::create([
            ...$validated,
            'section_id' => $section->id,
            'created_by' => $request->user()->id,
            'type' => $this->categoryType($category),
            'weight' => $category->weight,
            'sort_order' => $category->assessments()->max('sort_order') + 1,
            'status' => 'published',
            'published_at' => now(),
        ]);

        return back()->with('status', 'Score item added to the grading sheet.');
    }

    public function updateGradeItem(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        $this->access->ensureCanConfigureGrades($request->user());
        $validated = $request->validate([
            'grading_category_id' => ['required', 'integer', 'exists:grading_categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'max_score' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ]);
        $category = GradingCategory::where('section_id', $assessment->section_id)->findOrFail($validated['grading_category_id']);
        $assessment->update([...$validated, 'type' => $this->categoryType($category), 'weight' => $category->weight]);

        return back()->with('status', 'Score item updated.');
    }

    public function destroyGradeItem(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        $this->access->ensureCanConfigureGrades($request->user());
        $assessment->delete();

        return back()->with('status', 'Score item and its recorded scores were deleted.');
    }

    public function updateGradeScore(Request $request, NstpSection $section): JsonResponse
    {
        $this->access->ensureCanManageSection($request->user(), $section);
        $validated = $request->validate([
            'assessment_id' => ['required', 'integer', 'exists:assessments,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
            'score' => ['nullable', 'numeric', 'min:0'],
        ]);
        $assessment = Assessment::where('section_id', $section->id)->findOrFail($validated['assessment_id']);
        abort_unless(NstpEnrollment::where('section_id', $section->id)->where('student_id', $validated['student_id'])->exists(), 404);
        if (($validated['score'] ?? null) !== null && (float) $validated['score'] > (float) $assessment->max_score) {
            throw ValidationException::withMessages(['score' => 'Score cannot exceed '.number_format((float) $assessment->max_score, 2).'.']);
        }

        if (($validated['score'] ?? null) === null) {
            AssessmentSubmission::where('assessment_id', $assessment->id)->where('student_id', $validated['student_id'])->update([
                'score' => null, 'graded_by' => null, 'graded_at' => null,
            ]);
        } else {
            AssessmentSubmission::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $validated['student_id']],
                ['submitted_at' => now(), 'score' => $validated['score'], 'graded_by' => $request->user()->id, 'graded_at' => now()],
            );
        }

        $student = NstpEnrollment::where('section_id', $section->id)->where('student_id', $validated['student_id'])->firstOrFail()->student;
        $summary = $this->grades->summary($student, $section->id);

        return response()->json([
            'message' => 'Score saved.',
            'percentage' => $summary['percentage'],
            'grade' => $summary['grade'],
            'categories' => $summary['categories']->mapWithKeys(fn ($item) => [(string) $item['category']->id => [
                'earned' => $item['earned'],
                'maximum' => $item['maximum'],
                'weighted' => $item['weighted_score'],
            ]]),
        ]);
    }

    private function ensureGradingStructure(NstpSection $section): void
    {
        GradingSetting::firstOrCreate(['section_id' => $section->id], $this->grades->defaultSettings());
        if ($section->gradingCategories()->exists()) {
            return;
        }

        $defaults = [
            ['name' => 'Class Standing', 'weight' => 20, 'color' => '#f59e0b'],
            ['name' => 'Requirements', 'weight' => 30, 'color' => '#db2777'],
            ['name' => 'Term Test', 'weight' => 30, 'color' => '#16a34a'],
            ['name' => 'Quizzes', 'weight' => 20, 'color' => '#2563eb'],
        ];
        foreach ($defaults as $sortOrder => $default) {
            $section->gradingCategories()->create([...$default, 'sort_order' => $sortOrder]);
        }
    }

    private function categoryType(GradingCategory $category): string
    {
        return match (strtolower($category->name)) {
            'quizzes', 'quiz' => 'quiz',
            'term test', 'exam', 'exams' => 'exam',
            'requirements', 'requirement' => 'project',
            default => 'activity',
        };
    }

    private function context(Request $request): array
    {
        return ['layout' => $this->access->layout($request->user()), 'routePrefix' => $this->access->routePrefix($request->user())];
    }
}
