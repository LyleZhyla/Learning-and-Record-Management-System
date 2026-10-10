@extends($layout)

@section('title', 'Task & Activity Monitoring')
@section('page-title', 'Task & Activity Monitoring')

@section('content')
    <section class="welcome-banner">
        <div><span class="eyebrow">Assignments and accomplishments</span><h2>Monitor project work from assignment to completion</h2><p>Track assignees, priorities, deadlines, progress, missing work, submissions, evidence, and reviewer decisions.</p></div>
        <a class="secondary-button" href="{{ route($routePrefix.'.community-projects.index') }}">Community projects →</a>
    </section>

    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="metric-grid">
        <article class="metric-card"><span class="metric-icon blue">▤</span><div><small>Total tasks</small><strong>{{ $metrics['total'] }}</strong><p>Visible assignments</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">!</span><div><small>Overdue</small><strong>{{ $metrics['overdue'] }}</strong><p>Past deadline</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">↑</span><div><small>For review</small><strong>{{ $metrics['submitted'] }}</strong><p>Submitted accomplishments</p></div></article>
        <article class="metric-card"><span class="metric-icon green">✓</span><div><small>Average progress</small><strong>{{ $metrics['average_progress'] }}%</strong><p>{{ $metrics['completed'] }} completed · {{ $metrics['missing'] }} pending/blocked</p></div></article>
    </section>

    @if($canManageTasks && $projects->isNotEmpty())
        <section class="card" style="padding:1.25rem;margin-bottom:1.25rem"><div class="section-heading"><div><span class="eyebrow">Assign work</span><h3>Create a project task</h3><p>Select an approved project before assigning a student and deadline.</p></div></div><div style="display:flex;gap:.6rem;flex-wrap:wrap">@foreach($projects as $project)<a class="secondary-button" href="{{ route($routePrefix.'.project-tasks.create', $project) }}">{{ $project->reference_number }} · {{ str($project->title)->limit(35) }}</a>@endforeach</div></section>
    @endif

    <section class="card user-table-card">
        <form method="GET" class="report-filter-grid" style="padding:1.25rem">
            <label class="field-group"><span>Search</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Task, project, or reference"></label>
            <label class="field-group"><span>Project</span><select name="project_id"><option value="">All projects</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>{{ $project->reference_number }} · {{ $project->title }}</option>@endforeach</select></label>
            <label class="field-group"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Priority</span><select name="priority"><option value="">All priorities</option>@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Attention</span><select name="show"><option value="all">All tasks</option><option value="overdue" @selected(($filters['show'] ?? '') === 'overdue')>Overdue only</option><option value="unassigned" @selected(($filters['show'] ?? '') === 'unassigned')>Unassigned only</option></select></label>
            <div class="report-filter-actions"><button class="filter-button" type="submit">Apply filters</button><a class="clear-filter" href="{{ route($routePrefix.'.project-tasks.index') }}">Clear</a></div>
        </form>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Task</th><th>Assigned to</th><th>Deadline</th><th>Priority</th><th>Progress</th><th>Status</th><th>Submission / review</th></tr></thead><tbody>
            @forelse($tasks as $task)
                <tr>
                    <td><strong>{{ $task->title }}</strong><small class="table-secondary-line"><a href="{{ route($routePrefix.'.community-projects.show', $task->project) }}">{{ $task->project->reference_number }} · {{ $task->project->title }}</a></small><small class="table-secondary-line">{{ str($task->description)->limit(90) }}</small></td>
                    <td>{{ $task->assignee?->name ?? 'Unassigned' }}</td>
                    <td><span class="{{ $task->isOverdue() ? 'field-error' : '' }}">{{ $task->due_at?->format('M d, Y g:i A') ?? 'No deadline' }}</span>@if($task->isOverdue())<small class="table-secondary-line">Overdue</small>@endif</td>
                    <td>{{ $task->priorityLabel() }}</td>
                    <td><div class="compact-progress"><span><i style="width:{{ $task->progress_percentage }}%"></i></span><small>{{ $task->progress_percentage }}%</small></div></td>
                    <td>{{ $task->statusLabel() }}</td>
                    <td>
                        @if($task->submission_notes)<p>{{ $task->submission_notes }}</p>@endif
                        @if($task->evidence_path)<a href="{{ route($routePrefix.'.project-tasks.evidence', $task) }}">Download evidence</a>@endif
                        @if($task->review_notes)<small class="table-secondary-line"><strong>Review:</strong> {{ $task->review_notes }}</small>@endif
                        @if(auth()->user()->isStudent() && $task->assigned_to === auth()->id() && !in_array($task->status, ['completed', 'cancelled'], true))
                            <details><summary>Update / submit accomplishment</summary><form method="POST" enctype="multipart/form-data" action="{{ route($routePrefix.'.project-tasks.submit', $task) }}" class="stack-form" style="min-width:20rem">@csrf @method('PUT')<label class="field-group"><span>Status</span><select name="status"><option value="in_progress">In progress</option><option value="blocked">Blocked</option><option value="submitted">Submit for review</option></select></label><label class="field-group"><span>Progress</span><input type="number" name="progress_percentage" min="0" max="100" value="{{ $task->progress_percentage }}" required></label><label class="field-group"><span>Accomplishment / blocker notes</span><textarea name="submission_notes" rows="3" maxlength="4000">{{ $task->submission_notes }}</textarea></label><label class="field-group"><span>Evidence (optional)</span><input type="file" name="evidence" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"></label><button class="primary-button compact" type="submit">Save task progress</button></form></details>
                        @elseif($canManageTasks)
                            <a class="secondary-button compact" href="{{ route($routePrefix.'.project-tasks.edit', $task) }}">Edit assignment</a>
                            @if($task->status === 'submitted')<details><summary>Review accomplishment</summary><form method="POST" action="{{ route($routePrefix.'.project-tasks.review', $task) }}" class="stack-form" style="min-width:19rem">@csrf @method('PUT')<label class="field-group"><span>Decision</span><select name="decision"><option value="complete">Approve and complete</option><option value="return">Return for additional work</option></select></label><label class="field-group"><span>Reviewer notes</span><textarea name="review_notes" rows="3" maxlength="3000"></textarea></label><button class="primary-button compact" type="submit">Save review</button></form></details>@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty-state"><strong>No project tasks found</strong><span>Create a task from an approved community project or adjust the filters.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
        @if($tasks->hasPages())<div class="pagination-row">{{ $tasks->links() }}</div>@endif
    </section>
@endsection
