@extends($layout)

@section('title', $task->exists ? 'Edit Project Task' : 'Assign Project Task')
@section('page-title', $task->exists ? 'Edit Project Task' : 'Assign Project Task')

@section('content')
    <section class="welcome-banner"><div><span class="eyebrow">{{ $project->reference_number }}</span><h2>{{ $task->exists ? 'Update task assignment' : 'Assign implementation work' }}</h2><p>{{ $project->title }} · {{ $project->component->code }} · {{ $project->section?->code ?? 'Component-wide' }}</p></div></section>
    @if($errors->any())<div class="alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if($project->approval_status !== 'approved')<div class="alert warning"><strong>Project approval required.</strong> Tasks cannot be assigned until an authorized reviewer approves this project.</div>@endif

    <form class="card" style="padding:1.25rem" method="POST" action="{{ $task->exists ? route($routePrefix.'.project-tasks.update', $task) : route($routePrefix.'.project-tasks.store', $project) }}">
        @csrf @if($task->exists) @method('PUT') @endif
        <div class="form-grid">
            <label class="field-group full"><span>Task title</span><input name="title" value="{{ old('title', $task->title) }}" maxlength="255" required></label>
            <label class="field-group full"><span>Instructions / expected output</span><textarea name="description" rows="5" maxlength="4000">{{ old('description', $task->description) }}</textarea></label>
            <label class="field-group"><span>Assigned student</span><select name="assigned_to"><option value="">Unassigned</option>@foreach($assignees as $student)<option value="{{ $student->id }}" @selected((string) old('assigned_to', $task->assigned_to) === (string) $student->id)>{{ $student->name }} · {{ $student->email }}</option>@endforeach</select></label>
            <label class="field-group"><span>Priority</span><select name="priority" required>@foreach($priorities as $value => $label)<option value="{{ $value }}" @selected(old('priority', $task->priority ?? 'normal') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="field-group"><span>Deadline</span><input type="datetime-local" name="due_at" value="{{ old('due_at', $task->due_at?->format('Y-m-d\TH:i')) }}"></label>
        </div>
        <div class="form-actions"><a class="secondary-button" href="{{ route($routePrefix.'.project-tasks.index', ['project_id' => $project->id]) }}">Cancel</a><button class="primary-button" type="submit" @disabled(!$task->exists && $project->approval_status !== 'approved')>{{ $task->exists ? 'Save task changes' : 'Assign task' }}</button></div>
    </form>
@endsection
