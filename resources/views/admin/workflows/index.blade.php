@extends($layout)

@section('title', 'Workflow Rules')
@section('page-title', 'Workflow Rules Builder')

@section('content')
<section class="workflow-builder-hero">
    <div><span class="eyebrow">Configuration layer</span><h2>Build and control operational workflows</h2><p>Arrange steps, choose manual or automatic execution, and enable only the rules required by your institution.</p></div>
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
            <article class="workflow-step">
                <span class="workflow-step-order">{{ $loop->iteration }}</span>
                <div class="workflow-step-copy"><strong>{{ $step['name'] }}</strong><p>{{ $step['description'] }}</p></div>
                <label class="workflow-step-switch"><input type="hidden" name="steps[{{ $step['key'] }}][enabled]" value="0"><input type="checkbox" name="steps[{{ $step['key'] }}][enabled]" value="1" @checked($step['enabled'])><span>Enabled</span></label>
                <label class="field-group compact"><span>Execution</span><select name="steps[{{ $step['key'] }}][mode]"><option value="manual" @selected($step['mode'] === 'manual')>Manual</option><option value="automatic" @selected($step['mode'] === 'automatic')>Automatic</option></select></label>
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
@endforeach
</div>
@endsection
