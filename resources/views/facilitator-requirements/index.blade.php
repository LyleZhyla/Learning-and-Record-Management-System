@extends($layout)

@section('title', 'Facilitator Requirements')
@section('page-title', 'Facilitator Requirements & Compliance')

@section('content')
<section class="welcome-banner"><div><span class="eyebrow">Requirements submission and verification</span><h2>{{ auth()->user()->isFacilitator() ? 'Complete your facilitator requirements' : 'Monitor facilitator compliance' }}</h2><p>Securely submit, verify, return, and track required facilitator documents.</p></div></section>

@if(auth()->user()->isFacilitator())
    <section class="metric-grid" aria-label="Facilitator compliance summary">
        <article class="metric-card"><span class="metric-icon blue">▧</span><div><small>Required</small><strong>{{ $requiredCount }}</strong><p>Active requirements</p></div></article>
        <article class="metric-card"><span class="metric-icon green">✓</span><div><small>Verified</small><strong>{{ $verifiedCount }}</strong><p>Approved documents</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">%</span><div><small>Compliance</small><strong>{{ $compliancePercentage }}%</strong><p>{{ $compliancePercentage === 100 ? 'Complete' : 'Action required' }}</p></div></article>
    </section>
    <section class="dashboard-grid two-column">
        @forelse($requirements as $requirement)
            @php($submission = $submissions->get($requirement->id))
            <article class="card" style="padding:1.25rem">
                <span class="eyebrow">{{ $requirement->component?->code ?? 'All NSTP' }} · {{ $requirement->is_required ? 'Required' : 'Optional' }}</span>
                <h3>{{ $requirement->title }}</h3><p>{{ $requirement->description }}</p>
                @if($requirement->instructions)<p><strong>Instructions:</strong> {{ $requirement->instructions }}</p>@endif
                <p><small>Accepted: {{ $requirement->acceptedTypesLabel() }} · Maximum {{ number_format($requirement->max_size_kb / 1024) }} MB</small></p>
                @if($submission)
                    <p><span class="status-badge {{ $submission->status === 'verified' ? 'active' : 'inactive' }}"><i></i>{{ $submission->statusLabel() }}</span> <a class="table-action" href="{{ route($routePrefix.'.facilitator-requirements.download', $submission) }}">Download submitted file</a></p>
                    @if($submission->review_notes)<div class="alert {{ $submission->status === 'verified' ? 'success' : 'danger' }}"><strong>Reviewer notes:</strong> {{ $submission->review_notes }}</div>@endif
                @else
                    <p><span class="status-badge inactive"><i></i>Missing</span></p>
                @endif
                <form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.submit', $requirement) }}" enctype="multipart/form-data" class="stack-form">@csrf
                    <label class="field-group"><span>{{ $submission ? 'Replace or resubmit file' : 'Upload file' }}</span><input type="file" name="file" required accept="{{ collect($requirement->accepted_extensions)->map(fn($extension) => '.'.$extension)->implode(',') }}"></label>
                    <label class="field-group"><span>Submission notes</span><textarea name="facilitator_notes" rows="2" maxlength="2000">{{ old('facilitator_notes', $submission?->facilitator_notes) }}</textarea></label>
                    <button class="primary-button" type="submit">{{ $submission ? 'Resubmit for verification' : 'Submit requirement' }}</button>
                </form>
            </article>
        @empty
            <article class="card"><div class="empty-state"><strong>No active requirements</strong><span>There are currently no facilitator documents assigned to your component.</span></div></article>
        @endforelse
    </section>
@else
    <section class="metric-grid" aria-label="Compliance overview">
        <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>Facilitators</small><strong>{{ $complianceRows->count() }}</strong><p>Within your scope</p></div></article>
        <article class="metric-card"><span class="metric-icon green">✓</span><div><small>Fully compliant</small><strong>{{ $complianceRows->where('percentage', 100)->count() }}</strong><p>All required files verified</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">◷</span><div><small>Pending review</small><strong>{{ $submissions->where('status', 'pending')->count() }}</strong><p>Awaiting decision</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">!</span><div><small>For correction</small><strong>{{ $submissions->whereIn('status', ['needs_correction', 'rejected'])->count() }}</strong><p>Returned submissions</p></div></article>
    </section>

    @if($isConfigurator)
        <section class="card" style="padding:1.25rem;margin-bottom:1.25rem"><span class="eyebrow">Configuration</span><h3>Create facilitator requirement</h3>
            <form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.store') }}" class="report-filter-grid">@csrf
                <label class="field-group"><span>Requirement title</span><input name="title" required maxlength="255"></label>
                <label class="field-group"><span>Component scope</span><select name="component_id"><option value="">All NSTP facilitators</option>@foreach($components as $component)<option value="{{ $component->id }}">{{ $component->code }}</option>@endforeach</select></label>
                <label class="field-group"><span>Maximum file size</span><select name="max_size_mb"><option value="5">5 MB</option><option value="10">10 MB</option><option value="15">15 MB</option><option value="25">25 MB</option></select></label>
                <label class="field-group"><span>Sort order</span><input type="number" name="sort_order" value="0" min="0" max="999" required></label>
                <label class="field-group full"><span>Description</span><textarea name="description" rows="2" maxlength="1000"></textarea></label>
                <label class="field-group full"><span>Instructions</span><textarea name="instructions" rows="2" maxlength="2000"></textarea></label>
                <fieldset class="field-group full"><legend>Accepted file types</legend><div style="display:flex;gap:1rem;flex-wrap:wrap">@foreach($extensionOptions as $extension)<label><input type="checkbox" name="accepted_extensions[]" value="{{ $extension }}" @checked($extension === 'pdf')> {{ strtoupper($extension) }}</label>@endforeach</div></fieldset>
                <label><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" checked> Required for compliance</label>
                <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                <div><button class="primary-button" type="submit">Create requirement</button></div>
            </form>
        </section>
    @endif

    <section class="card user-table-card">
        <div class="section-heading"><div><span class="eyebrow">Compliance dashboard</span><h3>Facilitator requirement status</h3></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Facilitator</th><th>Components</th><th>Verified</th><th>Pending</th><th>Correction</th><th>Missing</th><th>Compliance</th></tr></thead><tbody>@forelse($complianceRows as $row)<tr><td><strong>{{ $row['facilitator']->name }}</strong><small class="table-secondary-line">{{ $row['facilitator']->email }}</small></td><td>{{ $row['facilitator']->facilitatedSections->pluck('component.code')->unique()->filter()->implode(', ') ?: 'Unassigned' }}</td><td>{{ $row['verified'] }} / {{ $row['required'] }}</td><td>{{ $row['pending'] }}</td><td>{{ $row['correction'] }}</td><td>{{ $row['missing'] }}</td><td><strong>{{ $row['percentage'] }}%</strong></td></tr>@empty<tr><td colspan="7"><div class="empty-state"><strong>No facilitators in scope</strong></div></td></tr>@endforelse</tbody></table></div>
    </section>

    <section class="card user-table-card" style="margin-top:1.25rem">
        <div class="section-heading"><div><span class="eyebrow">Verification queue</span><h3>Submitted facilitator documents</h3></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Facilitator</th><th>Requirement</th><th>Submitted</th><th>Status</th><th>Review</th></tr></thead><tbody>@forelse($submissions as $submission)<tr><td>{{ $submission->facilitator->name }}</td><td><strong>{{ $submission->requirement->title }}</strong><small class="table-secondary-line">{{ $submission->requirement->component?->code ?? 'All NSTP' }} · <a href="{{ route($routePrefix.'.facilitator-requirements.download', $submission) }}">Download file</a></small></td><td>{{ $submission->submitted_at->format('M d, Y g:i A') }}<small class="table-secondary-line">{{ $submission->facilitator_notes }}</small></td><td>{{ $submission->statusLabel() }}</td><td><form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.review', $submission) }}" class="stack-form" style="min-width:18rem">@csrf @method('PUT')<label class="field-group"><span>Decision</span><select name="status" required>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($submission->status === $value)>{{ $label }}</option>@endforeach</select></label><label class="field-group"><span>Review notes</span><textarea name="review_notes" rows="2" maxlength="2000">{{ $submission->review_notes }}</textarea></label><button class="primary-button compact" type="submit">Save review</button></form></td></tr>@empty<tr><td colspan="5"><div class="empty-state"><strong>No submissions yet</strong><span>Uploaded facilitator requirements will appear here.</span></div></td></tr>@endforelse</tbody></table></div>
    </section>

    <section class="card user-table-card" style="margin-top:1.25rem">
        <div class="section-heading"><div><span class="eyebrow">Requirement definitions</span><h3>Configured facilitator documents</h3></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Requirement</th><th>Scope</th><th>Validation</th><th>Status</th>@if($isConfigurator)<th>Configuration</th>@endif</tr></thead><tbody>@forelse($requirements as $requirement)<tr><td><strong>{{ $requirement->title }}</strong><small class="table-secondary-line">{{ $requirement->description }}</small></td><td>{{ $requirement->component?->code ?? 'All NSTP' }}</td><td>{{ $requirement->acceptedTypesLabel() }} · {{ number_format($requirement->max_size_kb / 1024) }} MB</td><td>{{ $requirement->is_active ? 'Active' : 'Inactive' }} · {{ $requirement->is_required ? 'Required' : 'Optional' }}</td>@if($isConfigurator)<td><details><summary>Edit</summary><form method="POST" action="{{ route($routePrefix.'.facilitator-requirements.update', $requirement) }}" class="stack-form" style="min-width:22rem">@csrf @method('PUT')<label class="field-group"><span>Title</span><input name="title" value="{{ $requirement->title }}" required></label><label class="field-group"><span>Component</span><select name="component_id"><option value="">All NSTP</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected($requirement->component_id === $component->id)>{{ $component->code }}</option>@endforeach</select></label><label class="field-group"><span>Description</span><textarea name="description" rows="2">{{ $requirement->description }}</textarea></label><label class="field-group"><span>Instructions</span><textarea name="instructions" rows="2">{{ $requirement->instructions }}</textarea></label><fieldset><legend>File types</legend>@foreach($extensionOptions as $extension)<label><input type="checkbox" name="accepted_extensions[]" value="{{ $extension }}" @checked(in_array($extension, $requirement->accepted_extensions, true))> {{ strtoupper($extension) }}</label>@endforeach</fieldset><label class="field-group"><span>Maximum MB</span><input type="number" name="max_size_mb" value="{{ $requirement->max_size_kb / 1024 }}" min="1" max="25" required></label><label class="field-group"><span>Sort order</span><input type="number" name="sort_order" value="{{ $requirement->sort_order }}" min="0" max="999" required></label><label><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked($requirement->is_required)> Required</label><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($requirement->is_active)> Active</label><button class="primary-button compact" type="submit">Save requirement</button></form></details></td>@endif</tr>@empty<tr><td colspan="5"><div class="empty-state"><strong>No facilitator requirements configured</strong></div></td></tr>@endforelse</tbody></table></div>
    </section>
@endif
@endsection
