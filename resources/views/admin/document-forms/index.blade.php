@extends($layout)

@section('title', 'Documents & Forms')
@section('page-title', 'Documents & Forms Configuration')

@section('content')
<section class="page-actions">
    <div><span class="eyebrow">Configuration layer</span><h2>Document and form builder</h2><p>Create upload requirements and downloadable forms without changing application code.</p></div>
    <div><a class="secondary-outline-button" href="{{ route($routePrefix.'.document-reviews.index') }}">Review submissions</a> <a class="primary-button compact" href="{{ route($routePrefix.'.document-forms.create') }}">Create document/form</a></div>
</section>

<section class="card user-table-card">
    <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Document / Form</th><th>Audience</th><th>Submission</th><th>Validation</th><th>Responses</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        @forelse($forms as $form)
            <tr>
                <td><strong>{{ $form->title }}</strong><br><small class="muted-cell">{{ $form->categoryLabel() }}</small></td>
                <td>{{ $form->component?->code ?? 'All NSTP students' }}</td>
                <td>{{ $form->requires_submission ? ($form->is_required ? 'Required upload' : 'Optional upload') : 'Download only' }}</td>
                <td>{{ $form->requires_submission ? $form->acceptedTypesLabel().' · '.number_format($form->max_size_kb / 1024).' MB' : '—' }}</td>
                <td>{{ $form->submissions_count }}</td>
                <td><span class="status-badge {{ $form->is_active ? 'active' : 'inactive' }}"><i></i>{{ $form->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><a class="table-action" href="{{ route($routePrefix.'.document-forms.edit', $form) }}">Configure</a></td>
            </tr>
        @empty<tr><td colspan="7"><div class="empty-state"><strong>No configurable documents or forms yet</strong><span>Create the first requirement or downloadable template.</span></div></td></tr>@endforelse
        </tbody>
    </table></div>
</section>
@endsection
