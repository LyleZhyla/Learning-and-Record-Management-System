<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AiLearningRecommendation;
use App\Models\Assessment;
use App\Models\LearningMaterial;
use App\Services\OpenAiLearningRecommendationService;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class LearningRecommendationController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $page = $this->pageData($request);
        $savedRecommendation = $page['enrollment']
            ? AiLearningRecommendation::query()
                ->where('student_id', $request->user()->id)
                ->where('nstp_enrollment_id', $page['enrollment']->id)
                ->latest()
                ->first()
            : null;

        return view('student.learning-recommendations', $page + [
            'guidance' => $savedRecommendation?->guidance,
            'preferences' => $savedRecommendation?->preferences ?? [],
            'savedRecommendation' => $savedRecommendation,
        ]);
    }

    public function generate(Request $request, OpenAiLearningRecommendationService $recommender): View|RedirectResponse
    {
        $preferences = $request->validate([
            'study_goal' => ['required', Rule::in(['catch_up', 'prepare_assessment', 'improve_performance', 'deepen_understanding'])],
            'weekly_time' => ['required', Rule::in(['under_2', '2_to_4', '5_plus'])],
            'learning_style' => ['required', Rule::in(['reading', 'practice', 'visual', 'mixed'])],
            'specific_goal' => ['nullable', 'string', 'max:1000'],
        ]);
        $page = $this->pageData($request);

        if (! $page['enrollment']) {
            return back()->withErrors(['recommendations' => 'Complete your NSTP enrollment before requesting learning recommendations.'])->withInput();
        }
        if ($page['materials']->isEmpty()) {
            return back()->withErrors(['recommendations' => 'No published learning materials are available for your component and section yet.'])->withInput();
        }

        try {
            $guidance = $recommender->recommend($request->user(), $this->aiContext($page), $preferences);
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            return back()->withErrors([
                'recommendations' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'AI learning recommendations are temporarily unavailable. Please try again later.',
            ])->withInput();
        }

        AiLearningRecommendation::create([
            'student_id' => $request->user()->id,
            'nstp_enrollment_id' => $page['enrollment']->id,
            'preferences' => $preferences,
            'guidance' => $guidance,
        ]);

        return redirect()->route('student.recommendations.index')
            ->with('status', 'Your AI learning recommendation was generated and saved.');
    }

    /** @return array<string, mixed> */
    private function pageData(Request $request): array
    {
        $student = $request->user();
        $enrollment = $this->access->currentEnrollment($student);
        $materials = new EloquentCollection;
        $assessments = new EloquentCollection;

        if ($enrollment) {
            $materials = LearningMaterial::with(['component', 'section'])
                ->where('status', 'published')
                ->where('component_id', $enrollment->component_id)
                ->where(fn ($query) => $query->whereNull('section_id')->orWhere('section_id', $enrollment->section_id))
                ->latest('published_at')->limit(50)->get();

            if ($enrollment->section_id) {
                $assessments = Assessment::with(['submissions' => fn ($query) => $query->where('student_id', $student->id)])
                    ->where('section_id', $enrollment->section_id)
                    ->where('status', 'published')
                    ->latest('due_at')->limit(30)->get();
            }
        }

        return [
            'enrollment' => $enrollment,
            'materials' => $materials,
            'materialsById' => $materials->keyBy('id'),
            'assessments' => $assessments,
            'isConfigured' => filled(config('services.openai.api_key')),
        ];
    }

    /** @param array<string, mixed> $page */
    private function aiContext(array $page): array
    {
        return [
            'component' => $page['enrollment']->component->code,
            'section' => $page['enrollment']->section?->code,
            'materials' => $page['materials']->map(fn (LearningMaterial $material) => [
                'id' => $material->id,
                'title' => $material->title,
                'description' => $material->description,
                'scope' => $material->section_id ? 'student_section' : 'component_wide',
            ])->values()->all(),
            'assessment_progress' => $page['assessments']->map(function (Assessment $assessment): array {
                $submission = $assessment->submissions->first();

                return [
                    'title' => $assessment->title,
                    'type' => $assessment->type,
                    'status' => $submission
                        ? ($submission->score !== null ? 'graded' : 'submitted')
                        : ($assessment->due_at?->isPast() ? 'missing' : 'assigned'),
                    'released_score_percentage' => $submission?->score !== null && (float) $assessment->max_score > 0
                        ? round(((float) $submission->score / (float) $assessment->max_score) * 100, 1)
                        : null,
                    'due_date' => $assessment->due_at?->toDateString(),
                ];
            })->values()->all(),
        ];
    }
}
