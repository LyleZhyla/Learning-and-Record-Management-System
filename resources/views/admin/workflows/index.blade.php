@extends($layout)

@section('title', $module ? $module['label'].' Settings' : 'Workflow Rules')
@section('page-title', $module ? $module['label'].' Settings' : 'Workflow Rules Builder')

@section('content')
<section class="workflow-builder-hero">
    <div><span class="eyebrow">{{ $module ? 'Module configuration' : 'Configuration layer' }}</span><h2>{{ $module ? 'Configure '.$module['label'].' workflows' : 'Build and control operational workflows' }}</h2><p>Arrange steps, choose manual or automatic execution, and enable only the rules required by your institution.</p>@if($module)<a class="text-link" href="{{ route($module['route']) }}">← Back to {{ $module['label'] }}</a>@endif</div>
    <div><strong>{{ $workflows->count() }}</strong><span>configured workflows</span></div>
</section>

<div class="workflow-builder-grid">
@foreach($workflows as $workflow)
    <form method="POST" action="{{ route($routePrefix.'.workflows.update', $workflow) }}" class="card workflow-builder-card">
        @csrf @method('PUT')
        <header>
            <div><span class="workflow-key">{{ str($workflow->key)->replace('_', ' ') }}</span><input name="name" maxlength="120" value="{{ $workflow->name }}" aria-label="Workflow name" required></div>
            <label class="workflow-master-toggle"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($workflow->is_active)><span>{{ $workflow->is_active ? 'Active' : 'Inactive' }}</span></label>
        </header>
        <textarea name="description" rows="2" maxlength="1000" aria-label="Workflow description">{{ $workflow->description }}</textarea>
        <div class="workflow-step-list">
        @foreach($workflow->resolvedSteps() as $step)
            <article class="workflow-step {{ $step['type'] === 'manual_checkpoint' ? 'is-custom' : '' }}">
                <span class="workflow-step-order">{{ $loop->iteration }}</span>
                <div class="workflow-step-copy">
                    @if($step['type'] === 'manual_checkpoint')
                        <span class="workflow-custom-badge">Institution checkpoint</span>
                        <input name="steps[{{ $step['key'] }}][name]" value="{{ $step['name'] }}" maxlength="120" aria-label="Checkpoint name" required>
                        <textarea name="steps[{{ $step['key'] }}][description]" rows="2" maxlength="1000" aria-label="Checkpoint description">{{ $step['description'] }}</textarea>
                    @else
                        <strong>{{ $step['name'] }}</strong><p>{{ $step['description'] }}</p>
                    @endif
                </div>
                <label class="workflow-step-switch"><input type="hidden" name="steps[{{ $step['key'] }}][enabled]" value="0"><input type="checkbox" name="steps[{{ $step['key'] }}][enabled]" value="1" @checked($step['enabled'])><span>Enabled</span></label>
                @if($step['type'] === 'manual_checkpoint')
                    <input type="hidden" name="steps[{{ $step['key'] }}][mode]" value="manual">
                    <label class="field-group compact"><span>Responsible role</span><select name="steps[{{ $step['key'] }}][assigned_role]"><option value="any_admin" @selected($step['assigned_role'] === 'any_admin')>Any admin</option><option value="super_admin" @selected($step['assigned_role'] === 'super_admin')>Super Admin</option><option value="nstp_admin" @selected($step['assigned_role'] === 'nstp_admin')>NSTP Admin</option></select></label>
                    <label class="workflow-step-switch"><input type="hidden" name="steps[{{ $step['key'] }}][required]" value="0"><input type="checkbox" name="steps[{{ $step['key'] }}][required]" value="1" @checked($step['required'])><span>Required</span></label>
                    <button class="danger-link" type="submit" form="delete-step-{{ $workflow->id }}-{{ $step['key'] }}" onclick="return confirm('Remove this institutional checkpoint from the active workflow? Existing audit records will be retained.')">Remove</button>
                @else
                    <label class="field-group compact"><span>Execution</span><select name="steps[{{ $step['key'] }}][mode]"><option value="manual" @selected($step['mode'] === 'manual')>Manual</option><option value="automatic" @selected($step['mode'] === 'automatic')>Automatic</option></select></label>
                @endif
                <label class="field-group compact order"><span>Order</span><input type="number" name="steps[{{ $step['key'] }}][sort_order]" min="0" max="999" value="{{ $step['sort_order'] }}" required></label>
            </article>
        @endforeach
        </div>
        <footer>
            <small>
                @if($workflow->updated_at)
                    Last updated {{ $workflow->updated_at->diffForHumans() }} by {{ $workflow->updater?->name ?? 'System' }}
                @else
                    Using system defaults
                @endif
            </small>
            <button class="primary-button compact" type="submit">Save workflow</button>
        </footer>
    </form>
    @foreach(collect($workflow->resolvedSteps())->where('type', 'manual_checkpoint')->values() as $customStep)
        <form id="delete-step-{{ $workflow->id }}-{{ $customStep['key'] }}" method="POST" action="{{ route($routePrefix.'.workflows.steps.destroy', [$workflow, $customStep['key']]) }}">@csrf @method('DELETE')</form>
    @endforeach
    @if($workflow->allowsCustomCheckpoints())
        <form method="POST" action="{{ route($routePrefix.'.workflows.steps.store', $workflow) }}" class="card workflow-checkpoint-creator">
            @csrf
            <div class="card-heading"><div><span class="eyebrow">Institution-specific process</span><h3>Add a registration checkpoint</h3><p>Add approvals such as department endorsement, medical clearance, scholarship validation, or registrar confirmation without changing source code.</p></div></div>
            <div class="form-grid">
                <label class="field-group"><span>Checkpoint name</span><input name="name" maxlength="120" placeholder="e.g. Medical clearance" required></label>
                <label class="field-group"><span>Responsible role</span><select name="assigned_role" required><option value="any_admin">Any registration administrator</option><option value="nstp_admin">NSTP Admin</option><option value="super_admin">Super Admin</option></select></label>
                <label class="field-group full"><span>Instructions</span><textarea name="description" rows="3" maxlength="1000" placeholder="What must the reviewer verify before approving this checkpoint?"></textarea></label>
            </div>
            <div class="workflow-checkpoint-options">
                <label><input type="hidden" name="required" value="0"><input type="checkbox" name="required" value="1" checked><span><strong>Required before final approval</strong><small>The student account will not be provisioned until this checkpoint is approved.</small></span></label>
                <label><input type="hidden" name="apply_to_existing" value="0"><input type="checkbox" name="apply_to_existing" value="1"><span><strong>Apply to existing registrations</strong><small>Leave off to protect applications submitted before this process was introduced.</small></span></label>
            </div>
            <div class="form-actions"><button class="primary-button compact" type="submit">Add checkpoint</button></div>
        </form>
    @endif
@endforeach
</div>
@endsection
