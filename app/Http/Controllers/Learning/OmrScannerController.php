<?php

namespace App\Http\Controllers\Learning;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentSubmission;
use App\Models\NstpEnrollment;
use App\Models\OmrScanResult;
use App\Models\OmrSheet;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OmrScannerController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $assessments = $this->assessmentQuery($request->user())
            ->with('section.component')->whereDoesntHave('omrSheet')->latest()->get();
        $sheets = OmrSheet::with(['assessment.section.component', 'creator'])->withCount('results')
            ->whereHas('assessment', fn (Builder $query) => $this->scopeAssessments($query, $request->user()))
            ->latest()->paginate(12);

        return view('learning.omr.index', $this->context($request) + compact('assessments', 'sheets'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'assessment_id' => ['required', 'integer', 'exists:assessments,id', 'unique:omr_sheets,assessment_id'],
            'item_count' => ['required', 'integer', 'min:1', 'max:30'],
            'choice_count' => ['required', 'integer', 'min:2', 'max:5'],
            'answers' => ['required', 'array'],
            'answers.*' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])],
        ]);

        $assessment = Assessment::with('section')->findOrFail($validated['assessment_id']);
        $this->ensureCanUse($request->user(), $assessment);
        $answers = array_values($validated['answers']);

        if (count($answers) !== (int) $validated['item_count']) {
            throw ValidationException::withMessages(['answers' => 'Provide one correct answer for every item.']);
        }

        $allowed = array_slice(['A', 'B', 'C', 'D', 'E'], 0, (int) $validated['choice_count']);
        if (collect($answers)->contains(fn ($answer) => ! in_array($answer, $allowed, true))) {
            throw ValidationException::withMessages(['answers' => 'The answer key contains a choice outside the configured range.']);
        }

        $sheet = OmrSheet::create([
            'assessment_id' => $assessment->id,
            'created_by' => $request->user()->id,
            'item_count' => $validated['item_count'],
            'choice_count' => $validated['choice_count'],
            'answer_key' => $answers,
        ]);

        return redirect()->route($this->routePrefix($request).'.omr.show', $sheet)
            ->with('status', 'Answer sheet scanner created. Print the template before scanning student papers.');
    }

    public function show(Request $request, OmrSheet $sheet): View
    {
        $sheet->load(['assessment.section.component', 'results.student', 'results.scanner']);
        $this->ensureCanUse($request->user(), $sheet->assessment);
        $students = NstpEnrollment::with('student')
            ->where('section_id', $sheet->assessment->section_id)->where('status', 'enrolled')->get()
            ->sortBy(fn ($enrollment) => $enrollment->student->name)->values();

        return view('learning.omr.show', $this->context($request) + compact('sheet', 'students'));
    }

    public function printable(Request $request, OmrSheet $sheet): View
    {
        $sheet->load('assessment.section.component');
        $this->ensureCanUse($request->user(), $sheet->assessment);

        return view('learning.omr.print', compact('sheet'));
    }

    public function grade(Request $request, OmrSheet $sheet): JsonResponse
    {
        $sheet->load('assessment.section');
        $this->ensureCanUse($request->user(), $sheet->assessment);
        $validated = $request->validate([
            'student_id' => [
                'required', 'integer',
                Rule::exists('nstp_enrollments', 'student_id')->where('section_id', $sheet->assessment->section_id)->where('status', 'enrolled'),
            ],
            'answers' => ['required', 'array', 'size:'.$sheet->item_count],
            'answers.*' => ['nullable', Rule::in(array_slice(['A', 'B', 'C', 'D', 'E'], 0, $sheet->choice_count))],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $answers = array_map(fn ($answer) => $answer ?: null, array_values($validated['answers']));
        $correct = collect($answers)->filter(fn ($answer, $index) => $answer === $sheet->answer_key[$index])->count();
        $blank = collect($answers)->filter(fn ($answer) => $answer === null)->count();
        $score = round(($correct / $sheet->item_count) * (float) $sheet->assessment->max_score, 2);

        DB::transaction(function () use ($request, $sheet, $validated, $answers, $correct, $blank, $score): void {
            OmrScanResult::updateOrCreate(
                ['omr_sheet_id' => $sheet->id, 'student_id' => $validated['student_id']],
                ['scanned_by' => $request->user()->id, 'answers' => $answers, 'correct_count' => $correct, 'blank_count' => $blank, 'score' => $score, 'confidence' => $validated['confidence'] ?? null],
            );

            $submission = AssessmentSubmission::firstOrNew([
                'assessment_id' => $sheet->assessment_id,
                'student_id' => $validated['student_id'],
            ]);
            $submission->fill([
                'answer_text' => $submission->answer_text ?: 'Checked using the SNAPIE Answer Sheet Scanner.',
                'submitted_at' => $submission->submitted_at ?: now(),
                'score' => $score,
                'feedback' => "Automatically checked: {$correct} of {$sheet->item_count} correct".($blank ? ", {$blank} blank." : '.'),
                'graded_by' => $request->user()->id,
                'graded_at' => now(),
            ])->save();
        });

        return response()->json([
            'message' => "Answer sheet checked: {$correct}/{$sheet->item_count} correct.",
            'correct' => $correct,
            'blank' => $blank,
            'score' => $score,
            'max_score' => (float) $sheet->assessment->max_score,
        ]);
    }

    private function assessmentQuery(User $user): Builder
    {
        return $this->scopeAssessments(Assessment::query(), $user);
    }

    private function scopeAssessments(Builder $query, User $user): Builder
    {
        if ($user->isCoordinator()) {
            return $query;
        }

        return $query->whereHas('section', fn (Builder $section) => $section->where('facilitator_id', $user->id));
    }

    private function ensureCanUse(User $user, Assessment $assessment): void
    {
        abort_unless($user->isCoordinator() || ($user->isFacilitator() && $assessment->section->facilitator_id === $user->id), 403);
    }

    private function routePrefix(Request $request): string
    {
        return $this->access->routePrefix($request->user());
    }

    private function context(Request $request): array
    {
        return ['layout' => $this->access->layout($request->user()), 'routePrefix' => $this->routePrefix($request)];
    }
}
