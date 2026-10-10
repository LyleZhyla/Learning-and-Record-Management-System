<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\ComponentAssessmentSetting;
use App\Models\GradingCategory;
use App\Models\GradingSetting;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\OmrSheet;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\GradeService;
use App\Services\OpenAiAssessmentScoringService;
use App\Services\PortalAccessService;
use App\Services\StudentNotificationService;
use App\Services\SubmissionPreviewService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AssessmentController extends Controller
{
    public function __construct(
        private PortalAccessService $access,
        private GradeService $grades,
        private StudentNotificationService $studentNotifications,
        private OpenAiAssessmentScoringService $aiScoring,
        private SubmissionPreviewService $submissionPreviews,
    ) {}

    public function index(Request $request): View
    {
        $sectionIds = ($request->user()->isCoordinator()
            ? $this->access->gradebookSections($request->user())
            : $this->access->manageableSections($request->user()))->pluck('id');
        $assessments = Assessment::with(['section.component', 'creator', 'gradingCategory'])->withCount('submissions')
            ->whereIn('section_id', $sectionIds)->latest()->paginate(15);

        return view('learning.assessments.index', $this->context($request) + compact('assessments'));
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->isFacilitator() || $request->user()->isCoordinator(), 403);

        $sections = ($request->user()->isCoordinator() ? $this->access->gradebookSections($request->user()) : $this->access->manageableSections($request->user()))
            ->with('component.assessmentSetting')->where('status', 'active')->orderBy('code')->get();
        $sections->each(fn ($section) => $this->ensureGradingStructure($section));
        $sections->load('gradingCategories');

        return view('learning.assessments.create', $this->context($request) + [
            'sections' => $sections,
            'assessmentProfiles' => $sections->mapWithKeys(function (NstpSection $section): array {
                $profile = ComponentAssessmentSetting::configuredFor($section->component);

                return [(string) $section->id => [
                    'component' => $section->component->code,
                    'allowed_types' => $profile->allowed_types,
                    'default_type' => $profile->default_type,
                    'default_max_score' => (float) $profile->default_max_score,
                    'rubric_required_types' => $profile->rubric_required_types,
                ]];
            }),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isFacilitator() || $request->user()->isCoordinator(), 403);

        $request->merge(['create_answer_sheet' => $request->boolean('create_answer_sheet')]);
        $this->removeEmptyRubricRows($request);
        $validated = $request->validate([
            'section_id' => ['required', 'exists:nstp_sections,id'],
            'grading_category_id' => ['required', 'integer', 'exists:grading_categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['quiz', 'activity', 'project', 'exam'])],
            'instructions' => ['nullable', 'string'],
            'rubric_criteria' => ['nullable', 'array', 'max:10'],
            'rubric_criteria.*.title' => ['required', 'string', 'max:120'],
            'rubric_criteria.*.description' => ['required', 'string', 'max:1000'],
            'rubric_criteria.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'rubric_criteria.*.score' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:10000'],
            'weight' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'create_answer_sheet' => ['required', 'boolean'],
            'item_count' => ['nullable', 'required_if:create_answer_sheet,1', 'integer', 'min:1', 'max:30'],
            'choice_count' => ['nullable', 'required_if:create_answer_sheet,1', 'integer', 'min:2', 'max:5'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
        ]);
        $validated['rubric'] = $this->encodeRubric($validated['rubric_criteria'] ?? [], (float) $validated['max_score']);
        unset($validated['rubric_criteria']);
        $section = NstpSection::findOrFail($validated['section_id']);
        $section->loadMissing('component.assessmentSetting');
        $this->access->ensureCanAccessGradebookSection($request->user(), $section);
        $this->ensureGradingStructure($section);
        $profile = ComponentAssessmentSetting::configuredFor($section->component);
        if (! $profile->allows($validated['type'])) {
            throw ValidationException::withMessages(['type' => $section->component->code.' does not allow '.str($validated['type'])->headline().' assessments.']);
        }
        if ($profile->requiresRubric($validated['type']) && blank($validated['rubric'])) {
            throw ValidationException::withMessages(['rubric_criteria' => $section->component->code.' requires a scoring rubric for '.str($validated['type'])->headline().' assessments.']);
        }
        $category = GradingCategory::where('section_id', $section->id)->findOrFail($validated['grading_category_id']);
        if ($category->assessment_type && $category->assessment_type !== $validated['type']) {
            throw ValidationException::withMessages(['grading_category_id' => 'Select the configured '.$validated['type'].' category for this component.']);
        }
        $validated['grading_category_id'] = $category->id;
        $validated['weight'] = $category->weight;
        $createAnswerSheet = (bool) $validated['create_answer_sheet'];

        if ($createAnswerSheet) {
            abort_unless($request->user()->isFacilitator() || $request->user()->isCoordinator(), 403);
        }
        if ($createAnswerSheet && ! in_array($validated['type'], ['quiz', 'exam'], true)) {
            throw ValidationException::withMessages(['create_answer_sheet' => 'Answer sheets are available only for quiz and exam assessments.']);
        }

        $answers = array_values($validated['answers'] ?? []);
        if ($createAnswerSheet && $answers !== [] && count($answers) !== (int) $validated['item_count']) {
            throw ValidationException::withMessages(['answers' => 'Complete the answer key for every item, or leave all answers blank to add it later.']);
        }
        if ($createAnswerSheet) {
            $allowed = array_slice(['A', 'B', 'C', 'D', 'E'], 0, (int) $validated['choice_count']);
            if (collect($answers)->contains(fn ($answer) => ! in_array($answer, $allowed, true))) {
                throw ValidationException::withMessages(['answers' => 'The answer key contains a choice outside the configured range.']);
            }
        }

        [$assessment, $sheet] = DB::transaction(function () use ($request, $validated, $createAnswerSheet, $answers) {
            $assessmentData = collect($validated)->except(['create_answer_sheet', 'item_count', 'choice_count', 'answers'])->all();
            $assessment = Assessment::create([...$assessmentData, 'created_by' => $request->user()->id, 'published_at' => $validated['status'] === 'published' ? now() : null]);
            $sheet = $createAnswerSheet ? OmrSheet::create([
                'assessment_id' => $assessment->id,
                'created_by' => $request->user()->id,
                'item_count' => $validated['item_count'],
                'choice_count' => $validated['choice_count'],
                'answer_key' => $answers,
            ]) : null;

            return [$assessment, $sheet];
        });
        $this->studentNotifications->assessmentPublished($assessment);

        if ($sheet) {
            return redirect()->route($this->access->routePrefix($request->user()).'.omr.show', $sheet)
                ->with('status', $answers === []
                    ? 'Assessment and blank answer sheet created. Add the answer key before scanning.'
                    : 'Assessment and answer sheet created successfully.');
        }

        return redirect()->route($this->access->routePrefix($request->user()).'.assessments.show', $assessment)
            ->with('status', 'Assessment created successfully.');
    }

    public function show(Request $request, Assessment $assessment): View
    {
        $assessment->load(['section.component', 'gradingCategory']);
        if ($request->user()->isCoordinator()) {
            $this->access->ensureCanAccessGradebookSection($request->user(), $assessment->section);
        } else {
            $this->access->ensureCanManageSection($request->user(), $assessment->section);
        }
        $students = NstpEnrollment::with('student')
            ->where('section_id', $assessment->section_id)
            ->join('users', 'users.id', '=', 'nstp_enrollments.student_id')
            ->select('nstp_enrollments.*')
            ->orderBy('users.name')
            ->paginate(15)
            ->withQueryString();
        $assessment->load(['submissions' => fn ($query) => $query
            ->whereIn('student_id', $students->pluck('student_id'))
            ->with(['student', 'grader', 'aiApprover'])]);
        $submissionPreviews = $assessment->submissions->mapWithKeys(fn ($submission) => [$submission->id => $this->submissionPreviews->inspect($submission)]);

        return view('learning.assessments.show', $this->context($request) + compact('assessment', 'students', 'submissionPreviews'));
    }

    public function previewSubmissionFile(Request $request, Assessment $assessment, AssessmentSubmission $submission): BinaryFileResponse
    {
        $this->authorizeSubmissionFile($request, $assessment, $submission);
        $preview = $this->submissionPreviews->inspect($submission);
        abort_unless($preview['exists'], 404);
        abort_if($preview['too_large'] || $preview['preview_type'] === 'download', 422, 'This attachment must be downloaded for manual review.');

        return response()->file(Storage::path($submission->file_path), [
            'Content-Type' => $preview['mime'],
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $submission->original_filename ?: 'submission').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadSubmissionFile(Request $request, Assessment $assessment, AssessmentSubmission $submission): BinaryFileResponse
    {
        $this->authorizeSubmissionFile($request, $assessment, $submission);
        abort_unless($submission->file_path && Storage::exists($submission->file_path), 404);

        return response()->download(Storage::path($submission->file_path), $submission->original_filename ?: 'student-submission');
    }

    public function updateRubric(Request $request, Assessment $assessment): RedirectResponse
    {
        abort_unless($request->user()->isFacilitator(), 403);
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        $this->removeEmptyRubricRows($request);
        $validated = $request->validate([
            'rubric_criteria' => ['required', 'array', 'min:1', 'max:10'],
            'rubric_criteria.*.title' => ['required', 'string', 'max:120'],
            'rubric_criteria.*.description' => ['required', 'string', 'max:1000'],
            'rubric_criteria.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
            'rubric_criteria.*.score' => ['required', 'numeric', 'gt:0', 'max:'.$assessment->max_score],
        ]);
        $assessment->update(['rubric' => $this->encodeRubric($validated['rubric_criteria'], (float) $assessment->max_score)]);

        return back()->with('status', 'AI scoring rubric saved. Existing AI suggestions should be regenerated.');
    }

    public function suggestRubric(Request $request): JsonResponse
    {
        abort_unless($request->user()->isFacilitator(), 403);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(['quiz', 'activity', 'project', 'exam'])],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        try {
            return response()->json(['criteria' => $this->aiScoring->suggestRubric($request->user(), $validated)]);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            return response()->json([
                'message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'AI rubric suggestion is temporarily unavailable. Please try again later.',
            ], 422);
        }
    }

    public function generateAiScore(Request $request, Assessment $assessment, AssessmentSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->isFacilitator(), 403);
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        abort_unless($submission->assessment_id === $assessment->id, 404);
        $submission->loadMissing('assessment');

        try {
            $suggestion = $this->aiScoring->suggest($request->user(), $submission);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            return back()->withErrors([
                'ai_scoring' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'AI scoring is temporarily unavailable. Please try again later.',
            ]);
        }

        $requiresApproval = WorkflowDefinition::ruleEnabled('grading', 'require_ai_approval');
        $submission->update([
            'ai_suggested_score' => $suggestion['total_score'],
            'ai_feedback' => $suggestion['feedback'],
            'ai_breakdown' => [
                'criteria' => $suggestion['criteria'],
                'needs_manual_review' => (bool) $suggestion['needs_manual_review'],
            ],
            'ai_confidence' => $suggestion['confidence'],
            'ai_model' => $suggestion['model'],
            'ai_generated_at' => now(),
            'ai_approved_by' => $requiresApproval ? null : $request->user()->id,
            'ai_approved_at' => $requiresApproval ? null : now(),
            ...($requiresApproval ? [] : [
                'score' => $suggestion['total_score'],
                'feedback' => $suggestion['feedback'],
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ]),
        ]);

        $message = $requiresApproval
            ? 'AI score suggestion generated. Review it carefully before approval.'
            : 'AI score generated and applied automatically under the configured grading workflow.';

        return back()->with('status', $message)
            ->with('open_submission_modal', $submission->id);
    }

    public function approveAiScore(Request $request, Assessment $assessment, AssessmentSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->isFacilitator(), 403);
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        abort_unless($submission->assessment_id === $assessment->id, 404);
        abort_unless($submission->ai_generated_at !== null, 422);
        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assessment->max_score],
            'feedback' => ['nullable', 'string', 'max:3000'],
            'suggestion_generated_at' => ['required', 'date'],
        ]);

        if (! $submission->ai_generated_at->equalTo(Carbon::parse($validated['suggestion_generated_at']))) {
            throw ValidationException::withMessages([
                'ai_scoring' => 'This AI suggestion has changed. Review the latest suggestion before approving it.',
            ]);
        }

        $submission->update([
            'score' => $validated['score'],
            'feedback' => $validated['feedback'] ?? null,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'ai_approved_by' => $request->user()->id,
            'ai_approved_at' => now(),
        ]);

        return back()->with('status', 'AI-assisted score reviewed and approved as the official score.');
    }

    public function grade(Request $request, Assessment $assessment, AssessmentSubmission $submission): RedirectResponse
    {
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        abort_unless($submission->assessment_id === $assessment->id, 404);
        $validated = $request->validate(['score' => ['required', 'numeric', 'min:0', 'max:'.$assessment->max_score], 'feedback' => ['nullable', 'string', 'max:3000']]);
        $submission->update([
            ...$validated,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'ai_approved_by' => null,
            'ai_approved_at' => null,
        ]);

        return back()->with('status', 'Submission graded successfully.');
    }

    public function scoreStudent(Request $request, Assessment $assessment, User $student): RedirectResponse
    {
        $assessment->loadMissing(['section', 'gradingCategory']);
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        abort_unless(
            $student->isStudent()
                && NstpEnrollment::where('section_id', $assessment->section_id)
                    ->where('student_id', $student->id)
                    ->where('status', 'enrolled')
                    ->exists(),
            404,
        );

        $validated = $request->validate([
            'score' => ['required', 'numeric', 'min:0', 'max:'.$assessment->max_score],
            'feedback' => ['nullable', 'string', 'max:3000'],
        ]);
        $submission = AssessmentSubmission::firstOrNew([
            'assessment_id' => $assessment->id,
            'student_id' => $student->id,
        ]);
        $submission->fill([
            ...$validated,
            'submitted_at' => $submission->submitted_at ?? now(),
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'ai_approved_by' => null,
            'ai_approved_at' => null,
        ])->save();

        return back()->with(
            'status',
            'Score saved automatically under '.($assessment->gradingCategory?->name ?? 'the selected category').' in the grading sheet.',
        );
    }

    public function grades(Request $request): View
    {
        $sections = $this->access->gradebookSections($request->user())->with('component')->orderBy('code')->get();
        $components = $sections->pluck('component')->filter()->unique('id')->values();
        $requestedSection = $sections->firstWhere('id', $request->integer('section'));
        $selectedComponentId = $request->integer('component') ?: $requestedSection?->component_id ?: $components->first()?->id;

        if (! $components->contains('id', $selectedComponentId)) {
            $selectedComponentId = $components->first()?->id;
        }

        $componentSections = $sections->where('component_id', $selectedComponentId);
        $section = $componentSections->firstWhere('id', $request->integer('section')) ?? $componentSections->first();
        $rotcLevels = NstpEnrollment::ROTC_CATEGORIES;
        $selectedMsLevel = $section?->component?->code === 'ROTC' && array_key_exists($request->string('ms_level')->toString(), $rotcLevels)
            ? $request->string('ms_level')->toString()
            : null;
        $summaries = null;
        $categories = collect();
        $settings = null;
        $gradebookMetrics = [
            'students' => 0,
            'on_track' => 0,
            'needs_attention' => 0,
            'completed' => 0,
            'average_completion' => 0,
        ];

        if ($section) {
            $this->ensureGradingStructure($section);
            $section->load(['gradingCategories.assessments.submissions', 'gradingSetting']);
            $categories = $section->gradingCategories;
            $settings = $section->gradingSetting;
            $enrollments = $section->enrollments()->with('student');

            if ($selectedMsLevel) {
                $enrollments->where('rotc_category', $selectedMsLevel);
            }

            $allSummaries = $enrollments
                ->join('users', 'users.id', '=', 'nstp_enrollments.student_id')
                ->select('nstp_enrollments.*')
                ->orderBy('users.name')->get()->map(
                    fn ($enrollment) => ['student' => $enrollment->student] + $this->grades->summary($enrollment->student, $section->id),
                );
            $gradebookMetrics = [
                'students' => $allSummaries->count(),
                'on_track' => $allSummaries->where('progress_status', 'on_track')->count(),
                'needs_attention' => $allSummaries->whereIn('progress_status', ['at_risk', 'needs_improvement'])->count(),
                'completed' => $allSummaries->where('progress_status', 'completed')->count(),
                'average_completion' => round((float) ($allSummaries->avg('completion_percentage') ?? 0), 1),
            ];
            $page = LengthAwarePaginator::resolveCurrentPage();
            $summaries = new LengthAwarePaginator($allSummaries->forPage($page, 15)->values(), $allSummaries->count(), 15, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);
        }

        return view('learning.grades.index', $this->context($request) + compact(
            'sections',
            'components',
            'selectedComponentId',
            'rotcLevels',
            'selectedMsLevel',
            'section',
            'summaries',
            'categories',
            'settings',
            'gradebookMetrics',
        ));
    }

    public function updateGradeStructure(Request $request, NstpSection $section): RedirectResponse
    {
        $this->access->ensureCanAccessGradebookSection($request->user(), $section);
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

        if (WorkflowDefinition::ruleEnabled('grading', 'enforce_weight_total') && abs($totalWeight - 100) > 0.001) {
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
        $this->access->ensureCanAccessGradebookSection($request->user(), $category->section);
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
        $this->access->ensureCanAccessGradebookSection($request->user(), $section);
        $this->access->ensureCanConfigureGrades($request->user());
        $validated = $request->validate([
            'grading_category_id' => ['required', 'integer', 'exists:grading_categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'max_score' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ]);
        $category = GradingCategory::where('section_id', $section->id)->findOrFail($validated['grading_category_id']);
        $assessment = Assessment::create([
            ...$validated,
            'section_id' => $section->id,
            'created_by' => $request->user()->id,
            'type' => $this->categoryType($category),
            'weight' => $category->weight,
            'sort_order' => $category->assessments()->max('sort_order') + 1,
            'status' => 'published',
            'published_at' => now(),
        ]);
        $this->studentNotifications->assessmentPublished($assessment);

        return back()->with('status', 'Score item added to the grading sheet.');
    }

    public function updateGradeItem(Request $request, Assessment $assessment): RedirectResponse
    {
        $this->access->ensureCanAccessGradebookSection($request->user(), $assessment->section);
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
        $this->access->ensureCanAccessGradebookSection($request->user(), $assessment->section);
        $this->access->ensureCanConfigureGrades($request->user());
        $assessment->delete();

        return back()->with('status', 'Score item and its recorded scores were deleted.');
    }

    public function updateGradeScore(Request $request, NstpSection $section): JsonResponse
    {
        $this->access->ensureCanAccessGradebookSection($request->user(), $section);
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
                'ai_approved_by' => null, 'ai_approved_at' => null,
            ]);
        } else {
            AssessmentSubmission::updateOrCreate(
                ['assessment_id' => $assessment->id, 'student_id' => $validated['student_id']],
                [
                    'submitted_at' => now(), 'score' => $validated['score'],
                    'graded_by' => $request->user()->id, 'graded_at' => now(),
                    'ai_approved_by' => null, 'ai_approved_at' => null,
                ],
            );
        }

        $student = NstpEnrollment::where('section_id', $section->id)->where('student_id', $validated['student_id'])->firstOrFail()->student;
        $summary = $this->grades->summary($student, $section->id);

        return response()->json([
            'message' => 'Score saved.',
            'percentage' => $summary['percentage'],
            'grade' => $summary['grade'],
            'current_percentage' => $summary['current_percentage'],
            'completion_percentage' => $summary['completion_percentage'],
            'progress_status' => $summary['progress_status'],
            'progress_label' => $summary['progress_label'],
            'pending_count' => $summary['pending_count'],
            'categories' => $summary['categories']->mapWithKeys(fn ($item) => [(string) $item['category']->id => [
                'earned' => $item['earned'],
                'maximum' => $item['maximum'],
                'weighted' => $item['weighted_score'],
            ]]),
        ]);
    }

    private function ensureGradingStructure(NstpSection $section): void
    {
        $this->grades->ensureStructure($section);
    }

    private function categoryType(GradingCategory $category): string
    {
        if ($category->assessment_type) {
            return $category->assessment_type;
        }

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

    private function removeEmptyRubricRows(Request $request): void
    {
        $criteria = collect($request->input('rubric_criteria', []))
            ->filter(fn ($item) => is_array($item) && collect($item)->contains(fn ($value) => filled($value)))
            ->values()->all();
        $request->merge(['rubric_criteria' => $criteria]);
    }

    private function authorizeSubmissionFile(Request $request, Assessment $assessment, AssessmentSubmission $submission): void
    {
        abort_unless($request->user()->isFacilitator(), 403);
        $this->access->ensureCanManageSection($request->user(), $assessment->section);
        abort_unless($submission->assessment_id === $assessment->id, 404);
    }

    private function encodeRubric(array $criteria, float $maxScore): ?string
    {
        if ($criteria === []) {
            return null;
        }

        $percentageTotal = collect($criteria)->sum(fn ($criterion) => (float) $criterion['percentage']);
        $scoreTotal = collect($criteria)->sum(fn ($criterion) => (float) $criterion['score']);
        if (abs($percentageTotal - 100) > 0.01) {
            throw ValidationException::withMessages(['rubric_criteria' => 'Rubric percentages must total exactly 100%. Current total: '.number_format($percentageTotal, 2).'%.']);
        }
        if (abs($scoreTotal - $maxScore) > 0.01) {
            throw ValidationException::withMessages(['rubric_criteria' => 'Rubric scores must total the assessment maximum of '.number_format($maxScore, 2).'. Current total: '.number_format($scoreTotal, 2).'.']);
        }

        return json_encode([
            'version' => 1,
            'criteria' => collect($criteria)->map(fn ($criterion) => [
                'title' => trim($criterion['title']),
                'description' => trim($criterion['description']),
                'percentage' => round((float) $criterion['percentage'], 2),
                'score' => round((float) $criterion['score'], 2),
            ])->values()->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
