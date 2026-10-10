@extends($layout)

@section('title', 'Document Reviews')
@section('page-title', 'Document Submission Reviews')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">Documents &amp; forms</span><h2>Review student submissions</h2><p>Verify uploaded requirements or return them to students with clear correction notes.</p></div>
    <a class="secondary-outline-button" href="{{ route($routePrefix.'.document-forms.index') }}">Configure documents &amp; forms</a>
</section>

<section class="card document-filter-card">
    <form method="GET" class="document-filter-form">
        <label class="field-group"><span>Search student</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"></label>
        <label class="field-group"><span>Document/form</span><select name="document_form_id"><option value="">All items</option>@foreach($forms as $form)<option value="{{ $form->id }}" @selected((string)($filters['document_form_id'] ?? '') === (string)$form->id)>{{ $form->title }}</option>@endforeach</select></label>
        <label class="field-group"><span>Component</span><select name="component_id"><option value="">All components</option>@foreach($components as $component)<option value="{{ $component->id }}" @selected((string)($filters['component_id'] ?? '') === (string)$component->id)>{{ $component->code }}</option>@endforeach</select></label>
        <label class="field-group"><span>Status</span><select name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="primary-button compact" type="submit">Apply filters</button>
        <a class="cancel-button" href="{{ route($routePrefix.'.document-reviews.index') }}">Reset</a>
    </form>
</section>

<section class="document-review-list">
    @forelse($submissions as $submission)
        <article class="card document-review-card">
            <div class="document-review-summary">
                <div>
                    <span class="eyebrow">{{ $submission->documentForm->component?->code ?? 'All NSTP' }} · {{ $submission->academic_year ?: 'No term' }} {{ $submission->semester ? '· '.str($submission->semester)->headline() : '' }}</span>
                    <h3>{{ $submission->documentForm->title }}</h3>
                    <p><strong>{{ $submission->user->name }}</strong> · {{ $submission->user->email }}</p>
                    <small>Submitted {{ $submission->created_at->format('M j, Y g:i A') }} · {{ $submission->original_filename }}</small>
                </div>
                <span class="document-status {{ $submission->status }}">{{ $submission->statusLabel() }}</span>
            </div>
            <div class="document-review-actions">
                <div class="document-file-actions">
                    <a class="secondary-outline-button" href="{{ route($routePrefix.'.document-reviews.file', $submission) }}" target="_blank" rel="noopener">Preview file</a>
                    <a class="text-link" href="{{ route($routePrefix.'.document-reviews.download', $submission) }}">Download</a>
                </div>
                <form method="POST" action="{{ route($routePrefix.'.document-reviews.update', $submission) }}" class="document-review-form">
                    @csrf @method('PATCH')
                    <label class="field-group"><span>Review decision</span><select name="status" required><option value="pending" @selected($submission->status === 'pending')>Pending review</option><option value="verified" @selected($submission->status === 'verified')>Verified</option><option value="needs_correction" @selected($submission->status === 'needs_correction')>Needs correction</option></select></label>
                    <label class="field-group grow"><span>Review notes <small>(required for correction)</small></span><textarea name="review_notes" rows="2" maxlength="2000" placeholder="Explain what the student needs to correct">{{ $submission->review_notes }}</textarea></label>
                    <button class="primary-button compact" type="submit">Save review</button>
                </form>
            </div>
        </article>
    @empty
        <section class="card"><div class="empty-state"><strong>No matching submissions</strong><span>Student uploads will appear here for verification.</span></div></section>
    @endforelse
</section>

{{ $submissions->links() }}
@endsection
