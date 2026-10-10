<?php

namespace App\Http\Controllers;

use App\Models\CommunityProject;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectTaskController extends Controller
{
    public function __construct(private PortalAccessService $access) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(ProjectTask::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(ProjectTask::PRIORITIES))],
            'project_id' => ['nullable', 'integer', 'exists:community_projects,id'],
            'show' => ['nullable', Rule::in(['all', 'overdue', 'unassigned'])],
        ]);
        $baseQuery = $this->applyFilters($this->visibleTasks($request), $filters);
        $metrics = [
            'total' => (clone $baseQuery)->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'submitted' => (clone $baseQuery)->where('status', 'submitted')->count(),
            'overdue' => (clone $baseQuery)->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now())->count(),
            'missing' => (clone $baseQuery)->whereIn('status', ['pending', 'blocked'])->count(),
            'average_progress' => round((float) ((clone $baseQuery)->avg('progress_percentage') ?? 0)),
        ];
        $tasks = $baseQuery->with(['project.component', 'project.section', 'assignee', 'assigner', 'reviewer'])
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')->orderBy('due_at')->latest('id')
            ->paginate(15)->withQueryString();

        return view('project-tasks.index', $this->viewContext($request) + [
            'tasks' => $tasks,
            'filters' => $filters,
            'metrics' => $metrics,
            'projects' => $this->visibleProjects($request)->orderBy('title')->get(),
        ]);
    }

    public function create(Request $request, CommunityProject $communityProject): View
    {
        $this->ensureCanManageProject($request, $communityProject);

        return view('project-tasks.form', $this->viewContext($request) + [
            'task' => new ProjectTask,
            'project' => $communityProject,
            'assignees' => $this->assignableStudents($communityProject),
        ]);
    }

    public function store(Request $request, CommunityProject $communityProject): RedirectResponse
    {
        $this->ensureCanManageProject($request, $communityProject);
        abort_unless($communityProject->approval_status === 'approved', 422, 'Approve the project before assigning implementation tasks.');
        $validated = $this->taskData($request, $communityProject);
        $communityProject->tasks()->create([
            ...$validated,
            'assigned_by' => $request->user()->id,
            'status' => 'pending',
            'progress_percentage' => 0,
        ]);

        return redirect()->route($this->routePrefix($request).'.project-tasks.index', ['project_id' => $communityProject->id])
            ->with('status', 'Project task assigned successfully.');
    }

    public function edit(Request $request, ProjectTask $projectTask): View
    {
        $this->ensureCanManageProject($request, $projectTask->project);

        return view('project-tasks.form', $this->viewContext($request) + [
            'task' => $projectTask,
            'project' => $projectTask->project,
            'assignees' => $this->assignableStudents($projectTask->project),
        ]);
    }

    public function update(Request $request, ProjectTask $projectTask): RedirectResponse
    {
        $this->ensureCanManageProject($request, $projectTask->project);
        $validated = $this->taskData($request, $projectTask->project);
        $projectTask->update($validated);

        return redirect()->route($this->routePrefix($request).'.project-tasks.index', ['project_id' => $projectTask->community_project_id])
            ->with('status', 'Project task updated.');
    }

    public function submit(Request $request, ProjectTask $projectTask): RedirectResponse
    {
        abort_unless($request->user()->isStudent() && $projectTask->assigned_to === $request->user()->id, 403);
        abort_if(in_array($projectTask->status, ['completed', 'cancelled'], true), 422, 'This task can no longer be submitted.');
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'blocked', 'submitted'])],
            'progress_percentage' => ['required', 'integer', 'min:0', 'max:100'],
            'submission_notes' => [Rule::requiredIf(in_array($request->input('status'), ['blocked', 'submitted'], true)), 'nullable', 'string', 'max:4000'],
            'evidence' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);
        if ($validated['status'] === 'submitted' && (int) $validated['progress_percentage'] !== 100) {
            throw ValidationException::withMessages(['progress_percentage' => 'Set progress to 100% before submitting the accomplishment for review.']);
        }
        $newEvidence = $request->file('evidence')?->store('project-task-evidence', 'local');
        $oldEvidence = $projectTask->evidence_path;
        $projectTask->update([
            'status' => $validated['status'],
            'progress_percentage' => $validated['progress_percentage'],
            'submission_notes' => $validated['submission_notes'] ?? null,
            'evidence_path' => $newEvidence ?: $oldEvidence,
            'evidence_original_name' => $newEvidence ? $request->file('evidence')->getClientOriginalName() : $projectTask->evidence_original_name,
            'submitted_at' => $validated['status'] === 'submitted' ? now() : null,
            'review_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
        if ($newEvidence && $oldEvidence) {
            Storage::disk('local')->delete($oldEvidence);
        }

        return back()->with('status', $validated['status'] === 'submitted' ? 'Accomplishment submitted for review.' : 'Task progress updated.');
    }

    public function review(Request $request, ProjectTask $projectTask): RedirectResponse
    {
        $this->ensureCanManageProject($request, $projectTask->project);
        abort_unless($projectTask->status === 'submitted', 422, 'Only submitted accomplishments can be reviewed.');
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['complete', 'return'])],
            'review_notes' => [Rule::requiredIf($request->input('decision') === 'return'), 'nullable', 'string', 'max:3000'],
        ]);
        $completed = $validated['decision'] === 'complete';
        $projectTask->update([
            'status' => $completed ? 'completed' : 'in_progress',
            'progress_percentage' => $completed ? 100 : 90,
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'completed_at' => $completed ? now() : null,
        ]);

        return back()->with('status', $completed ? 'Accomplishment approved and task completed.' : 'Task returned for additional work.');
    }

    public function evidence(Request $request, ProjectTask $projectTask): StreamedResponse
    {
        abort_unless($this->visibleTasks($request)->whereKey($projectTask->id)->exists(), 403);
        abort_unless($projectTask->evidence_path && Storage::disk('local')->exists($projectTask->evidence_path), 404);

        return Storage::disk('local')->download($projectTask->evidence_path, $projectTask->evidence_original_name ?: 'task-evidence');
    }

    private function taskData(Request $request, CommunityProject $project): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', Rule::in(array_keys(ProjectTask::PRIORITIES))],
            'due_at' => ['nullable', 'date'],
        ]);
        if (filled($validated['assigned_to'] ?? null) && ! $this->assignableStudents($project)->contains('id', (int) $validated['assigned_to'])) {
            throw ValidationException::withMessages(['assigned_to' => 'Assign the task only to an enrolled student within this project scope.']);
        }

        return $validated;
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn (Builder $builder, string $search) => $builder->where(fn (Builder $nested) => $nested
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('project', fn (Builder $project) => $project->where('title', 'like', "%{$search}%")->orWhere('reference_number', 'like', "%{$search}%"))))
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $builder, string $priority) => $builder->where('priority', $priority))
            ->when($filters['project_id'] ?? null, fn (Builder $builder, int $projectId) => $builder->where('community_project_id', $projectId))
            ->when(($filters['show'] ?? null) === 'overdue', fn (Builder $builder) => $builder->whereNotIn('status', ['completed', 'cancelled'])->where('due_at', '<', now()))
            ->when(($filters['show'] ?? null) === 'unassigned', fn (Builder $builder) => $builder->whereNull('assigned_to'));
    }

    private function assignableStudents(CommunityProject $project)
    {
        return User::query()->where('role', 'student')->where('status', 'active')
            ->whereHas('nstpEnrollments', fn (Builder $enrollment) => $enrollment
                ->where('status', 'enrolled')
                ->where('component_id', $project->component_id)
                ->when($project->section_id, fn (Builder $query, int $sectionId) => $query->where('section_id', $sectionId)))
            ->orderBy('name')->get();
    }

    private function visibleTasks(Request $request): Builder
    {
        $user = $request->user();
        $query = ProjectTask::query();
        if ($user->isCoordinator()) {
            $query->whereHas('project', fn (Builder $project) => $project->where('component_id', $user->nstp_component_id ?? 0));
        } elseif ($user->isFacilitator()) {
            $query->whereHas('project.section', fn (Builder $section) => $section->where('facilitator_id', $user->id));
        } elseif ($user->isStudent()) {
            $query->where('assigned_to', $user->id);
        } elseif (! $user->isSuperAdmin() && ! $user->isNstpAdmin()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function visibleProjects(Request $request): Builder
    {
        $user = $request->user();
        $query = CommunityProject::query();
        if ($user->isCoordinator()) {
            $query->where('component_id', $user->nstp_component_id ?? 0);
        } elseif ($user->isFacilitator()) {
            $query->whereHas('section', fn (Builder $section) => $section->where('facilitator_id', $user->id));
        } elseif ($user->isStudent()) {
            $query->whereHas('tasks', fn (Builder $task) => $task->where('assigned_to', $user->id));
        }

        return $query;
    }

    private function ensureCanManageProject(Request $request, CommunityProject $project): void
    {
        $user = $request->user();
        abort_unless(
            $user->isSuperAdmin()
            || $user->isNstpAdmin()
            || ($user->isCoordinator() && $project->component_id === $user->nstp_component_id)
            || ($user->isFacilitator() && $project->section?->facilitator_id === $user->id),
            403,
        );
    }

    private function viewContext(Request $request): array
    {
        return [
            'layout' => $this->access->layout($request->user()),
            'routePrefix' => $this->routePrefix($request),
            'statuses' => ProjectTask::STATUSES,
            'priorities' => ProjectTask::PRIORITIES,
            'canManageTasks' => ! $request->user()->isStudent(),
        ];
    }

    private function routePrefix(Request $request): string
    {
        return $this->access->routePrefix($request->user());
    }
}
