<?php

namespace App\Http\Controllers;

use App\Models\CommunityProject;
use App\Models\EvaluationResponse;
use App\Models\NstpEnrollment;
use App\Models\NstpSection;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $sections = $this->visibleSections($user)
            ->with(['component', 'facilitator', 'enrollments' => fn ($query) => $query->where('status', 'enrolled')->with('student')])
            ->orderByDesc('academic_year')->orderBy('code')->get();
        $sectionIds = $sections->pluck('id');
        $studentInstructorResponses = EvaluationResponse::where('type', 'student_instructor')
            ->whereIn('section_id', $sectionIds)->get();
        $sectionSummaries = $sections->map(function (NstpSection $section) use ($studentInstructorResponses): array {
            $responses = $studentInstructorResponses->where('section_id', $section->id);

            return [
                'section' => $section,
                'responses' => $responses->count(),
                'average' => $responses->isEmpty() ? null : round($responses->avg(fn (EvaluationResponse $response) => $response->averageRating()), 2),
            ];
        });

        $enrollment = $user->isStudent() ? $this->access->currentEnrollment($user) : null;
        $studentInstructorEvaluation = $enrollment ? EvaluationResponse::where([
            'type' => 'student_instructor',
            'section_id' => $enrollment->section_id,
            'evaluator_id' => $user->id,
        ])->first() : null;
        $receivedEvaluations = $user->isStudent()
            ? EvaluationResponse::with(['section', 'evaluator'])->where('type', 'instructor_student')->where('subject_user_id', $user->id)->latest('submitted_at')->get()
            : collect();
        $instructorStudentResponses = $user->isStudent()
            ? collect()
            : EvaluationResponse::with(['section', 'subject', 'evaluator'])
                ->where('type', 'instructor_student')->whereIn('section_id', $sectionIds)->latest('submitted_at')->get();
        $instructorStudentEvaluations = $instructorStudentResponses
            ->keyBy(fn (EvaluationResponse $evaluation) => $evaluation->section_id.'-'.$evaluation->subject_user_id);
        $communityProjects = $this->visibleCommunityProjects($user)
            ->with(['component', 'evaluations' => fn ($query) => $query->where('type', 'community_feedback')])
            ->latest()->get();

        return view('evaluations.index', [
            'layout' => $this->access->layout($user),
            'routePrefix' => $this->access->routePrefix($user),
            'criteria' => EvaluationResponse::CRITERIA,
            'sections' => $sections,
            'sectionSummaries' => $sectionSummaries,
            'enrollment' => $enrollment,
            'studentInstructorEvaluation' => $studentInstructorEvaluation,
            'receivedEvaluations' => $receivedEvaluations,
            'instructorStudentEvaluations' => $instructorStudentEvaluations,
            'instructorStudentResponses' => $instructorStudentResponses,
            'communityProjects' => $communityProjects,
        ]);
    }

    public function storeStudentInstructor(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isStudent(), 403);
        $enrollment = $this->access->currentEnrollment($request->user());
        abort_unless($enrollment?->section?->facilitator_id, 422, 'Your active section must have an assigned facilitator before evaluation.');
        $validated = $request->validate($this->ratingRules('student_instructor'));

        EvaluationResponse::updateOrCreate([
            'type' => 'student_instructor',
            'section_id' => $enrollment->section_id,
            'evaluator_id' => $request->user()->id,
            'subject_user_id' => $enrollment->section->facilitator_id,
        ], [
            'answers' => $validated['ratings'],
            'comments' => $validated['comments'] ?? null,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Your instructor evaluation was submitted. Results are shown only in aggregate.');
    }

    public function storeInstructorStudent(Request $request, NstpSection $section, User $student): RedirectResponse
    {
        abort_unless($request->user()->isFacilitator() && $section->facilitator_id === $request->user()->id, 403);
        abort_unless(NstpEnrollment::where('section_id', $section->id)->where('student_id', $student->id)->where('status', 'enrolled')->exists(), 403);
        $validated = $request->validate($this->ratingRules('instructor_student'));

        EvaluationResponse::updateOrCreate([
            'type' => 'instructor_student',
            'section_id' => $section->id,
            'evaluator_id' => $request->user()->id,
            'subject_user_id' => $student->id,
        ], [
            'answers' => $validated['ratings'],
            'comments' => $validated['comments'] ?? null,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Student participation evaluation saved.');
    }

    public function toggleCommunitySurvey(Request $request, CommunityProject $communityProject): RedirectResponse
    {
        $this->ensureCanManageProject($request->user(), $communityProject);
        abort_unless($communityProject->approval_status === 'approved', 422, 'Approve the project before opening beneficiary feedback.');
        $validated = $request->validate(['feedback_is_open' => ['required', 'boolean']]);
        $communityProject->update(['feedback_is_open' => $validated['feedback_is_open']]);

        return back()->with('status', $validated['feedback_is_open'] ? 'Community feedback survey opened.' : 'Community feedback survey closed.');
    }

    public function communityForm(CommunityProject $communityProject, string $token): View
    {
        $this->ensureOpenCommunitySurvey($communityProject, $token);
        $communityProject->load(['component', 'section']);

        return view('evaluations.community', [
            'project' => $communityProject,
            'token' => $token,
            'criteria' => EvaluationResponse::CRITERIA['community_feedback'],
        ]);
    }

    public function storeCommunity(Request $request, CommunityProject $communityProject, string $token): RedirectResponse
    {
        $this->ensureOpenCommunitySurvey($communityProject, $token);
        $validated = $request->validate($this->ratingRules('community_feedback') + [
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'respondent_relationship' => ['required', 'string', 'max:255'],
        ]);
        EvaluationResponse::create([
            'type' => 'community_feedback',
            'section_id' => $communityProject->section_id,
            'community_project_id' => $communityProject->id,
            'respondent_name' => $validated['respondent_name'] ?? null,
            'respondent_relationship' => $validated['respondent_relationship'],
            'answers' => $validated['ratings'],
            'comments' => $validated['comments'] ?? null,
            'submitted_at' => now(),
        ]);

        return back()->with('status', 'Thank you. Your community feedback has been submitted.');
    }

    private function ratingRules(string $type): array
    {
        $rules = [
            'ratings' => ['required', 'array'],
            'comments' => ['nullable', 'string', 'max:3000'],
        ];
        foreach (array_keys(EvaluationResponse::CRITERIA[$type]) as $criterion) {
            $rules['ratings.'.$criterion] = ['required', 'integer', 'between:1,5'];
        }

        return $rules;
    }

    private function visibleSections(User $user): Builder
    {
        $query = NstpSection::query();
        if ($user->isCoordinator()) {
            $query->where('component_id', $user->nstp_component_id ?? 0);
        } elseif ($user->isFacilitator()) {
            $query->where('facilitator_id', $user->id);
        } elseif ($user->isStudent()) {
            $query->whereKey($this->access->currentEnrollment($user)?->section_id ?? 0);
        } elseif (! $user->isSuperAdmin() && ! $user->isNstpAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function visibleCommunityProjects(User $user): Builder
    {
        $query = CommunityProject::query();
        if ($user->isCoordinator()) {
            $query->where('component_id', $user->nstp_component_id ?? 0);
        } elseif ($user->isFacilitator()) {
            $query->whereHas('section', fn (Builder $section) => $section->where('facilitator_id', $user->id));
        } elseif ($user->isStudent()) {
            $query->whereRaw('1 = 0');
        } elseif (! $user->isSuperAdmin() && ! $user->isNstpAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function ensureCanManageProject(User $user, CommunityProject $project): void
    {
        abort_unless(
            $user->isSuperAdmin()
            || $user->isNstpAdmin()
            || ($user->isCoordinator() && $project->component_id === $user->nstp_component_id)
            || ($user->isFacilitator() && $project->section?->facilitator_id === $user->id),
            403,
        );
    }

    private function ensureOpenCommunitySurvey(CommunityProject $project, string $token): void
    {
        abort_unless(
            filled($project->feedback_token)
            && hash_equals($project->feedback_token, $token)
            && $project->feedback_is_open
            && $project->approval_status === 'approved',
            404,
        );
    }
}
