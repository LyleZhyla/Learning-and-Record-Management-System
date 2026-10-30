<?php

namespace App\Http\Controllers;

use App\Models\InterventionActivity;
use App\Models\NstpComponent;
use App\Models\NstpSection;
use App\Models\RiskThreshold;
use App\Models\StudentIntervention;
use App\Models\StudentRiskSnapshot;
use App\Models\User;
use App\Services\EarlyWarningService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EarlyWarningController extends Controller
{
    public function __construct(private EarlyWarningService $earlyWarning) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(array_keys(StudentIntervention::STATUSES))], 'search' => ['nullable', 'string', 'max:100']]);
        $query = $this->visibleInterventions($request->user())->with(['student', 'section.component', 'assignee', 'riskSnapshot'])->latest();
        $query->when($filters['status'] ?? null, fn ($items, $status) => $items->where('status', $status));
        $query->when(trim($filters['search'] ?? ''), fn ($items, $search) => $items->whereHas('student', fn ($students) => $students->where(fn ($names) => $names->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))));
        $interventions = $query->paginate($this->perPage())->withQueryString();
        $components = $this->visibleComponents($request->user())->get();
        $thresholds = RiskThreshold::whereIn('component_id', $components->pluck('id'))->get()->keyBy('component_id');
        $metrics = [
            'open' => $this->visibleInterventions($request->user())->whereIn('status', ['open', 'in_progress'])->count(),
            'escalated' => $this->visibleInterventions($request->user())->where('status', 'escalated')->count(),
            'resolved' => $this->visibleInterventions($request->user())->where('status', 'resolved')->count(),
        ];

        return view('early-warning.index', compact('interventions', 'components', 'thresholds', 'metrics', 'filters'));
    }

    public function show(Request $request, StudentIntervention $intervention): View
    {
        abort_unless($this->visibleInterventions($request->user())->whereKey($intervention->id)->exists(), 404);
        $intervention->load(['student', 'section.component', 'assignee', 'creator', 'riskSnapshot', 'activities.actor']);
        $snapshots = StudentRiskSnapshot::where('student_id', $intervention->student_id)->where('section_id', $intervention->section_id)->latest('calculated_at')->get();
        $assignees = $this->eligibleAssignees($intervention->section)->get();
        $canManage = ! $request->user()->isStudent();

        return view('early-warning.show', compact('intervention', 'snapshots', 'assignees', 'canManage'));
    }

    public function updateThreshold(Request $request, NstpComponent $component): RedirectResponse
    {
        $this->authorizeComponentManagement($request->user(), $component);
        $data = $request->validate([
            'minimum_attendance_percentage' => ['required', 'numeric', 'between:0,100'],
            'minimum_grade_percentage' => ['required', 'numeric', 'between:0,100'],
            'maximum_missing_requirements' => ['required', 'integer', 'between:0,100'],
            'follow_up_days' => ['required', 'integer', 'between:1,90'],
            'escalation_days' => ['required', 'integer', 'between:1,180'],
            'minimum_improvement_points' => ['required', 'integer', 'between:1,100'],
        ]);
        abort_if($data['escalation_days'] < $data['follow_up_days'], 422, 'Escalation days must be on or after the follow-up interval.');
        $threshold = $this->earlyWarning->thresholdFor($component->id);
        $threshold->update($data + ['updated_by' => $request->user()->id]);

        return back()->with('status', $component->code.' early-warning thresholds updated.');
    }

    public function update(Request $request, StudentIntervention $intervention): RedirectResponse
    {
        abort_if($request->user()->isStudent(), 403);
        abort_unless($this->visibleInterventions($request->user())->whereKey($intervention->id)->exists(), 404);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(array_keys(StudentIntervention::STATUSES))],
            'referral_type' => ['required', Rule::in(array_keys(StudentIntervention::REFERRAL_TYPES))],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'action_plan' => ['nullable', 'string', 'max:5000'],
            'follow_up_date' => ['nullable', 'date'],
            'resolution' => ['nullable', 'string', 'max:5000', Rule::requiredIf(fn () => $request->input('status') === 'resolved')],
        ]);
        if ($data['assigned_to']) {
            abort_unless($this->eligibleAssignees($intervention->section)->whereKey($data['assigned_to'])->exists(), 422, 'The selected adviser is not responsible for this component.');
        }
        $before = $intervention->only(array_keys($data));
        $data['last_reviewed_at'] = now();
        $data['resolved_at'] = $data['status'] === 'resolved' ? now() : null;
        if ($data['status'] === 'escalated' && $intervention->status !== 'escalated') {
            $data['escalated_at'] = now();
            $data['escalation_count'] = $intervention->escalation_count + 1;
        }
        $intervention->update($data);
        InterventionActivity::create([
            'intervention_id' => $intervention->id,
            'actor_id' => $request->user()->id,
            'action' => $data['status'] === 'resolved' ? 'resolved' : 'reviewed',
            'details' => $data['notes'] ?: 'Intervention record updated.',
            'changes' => collect($intervention->only(array_keys($before)))->filter(fn ($value, $key) => $value != $before[$key])->all(),
        ]);

        return back()->with('status', 'Intervention and follow-up history updated.');
    }

    private function visibleInterventions(User $user): Builder
    {
        return StudentIntervention::query()->when($user->isStudent(), fn ($query) => $query->where('student_id', $user->id))
            ->when($user->isFacilitator(), fn ($query) => $query->whereHas('section', fn ($sections) => $sections->where('facilitator_id', $user->id)))
            ->when($user->isCoordinator(), fn ($query) => $query->whereHas('section', fn ($sections) => $sections->where('component_id', $user->nstp_component_id ?? 0)));
    }

    private function visibleComponents(User $user): Builder
    {
        return NstpComponent::query()->when($user->isStudent(), fn ($query) => $query->whereRaw('1 = 0'))
            ->when($user->isFacilitator(), fn ($query) => $query->whereHas('sections', fn ($sections) => $sections->where('facilitator_id', $user->id)))
            ->when($user->isCoordinator(), fn ($query) => $query->whereKey($user->nstp_component_id ?? 0))->orderBy('code');
    }

    private function eligibleAssignees(NstpSection $section): Builder
    {
        return User::query()->where('status', 'active')->where(function ($query) use ($section): void {
            $query->whereIn('role', ['super_admin', 'nstp_admin'])
                ->orWhere(fn ($staff) => $staff->where('role', 'coordinator')->where('nstp_component_id', $section->component_id))
                ->orWhere(fn ($staff) => $staff->where('role', 'facilitator')->whereHas('facilitatedSections', fn ($sections) => $sections->whereKey($section->id)));
        })->orderBy('name');
    }

    private function authorizeComponentManagement(User $user, NstpComponent $component): void
    {
        abort_if($user->isStudent() || $user->isFacilitator(), 403);
        abort_if($user->isCoordinator() && (int) $user->nstp_component_id !== $component->id, 403);
    }
}
