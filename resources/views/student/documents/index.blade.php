@extends('layouts.student')

@section('title', 'My Documents & Forms')
@section('page-title', 'My Documents & Forms')

@section('content')
<section class="document-student-hero">
    <div><span class="eyebrow">Configurable requirements</span><h2>Your NSTP documents in one place</h2><p>Download official forms, follow the configured instructions, and monitor the review status of every upload.</p></div>
    <div class="document-term-summary"><span>Current assignment</span><strong>{{ $enrollment?->component?->code ?? 'Not assigned' }}</strong><small>{{ $enrollment ? $enrollment->academic_year.' · '.str($enrollment->semester)->headline() : 'Select an NSTP component to see targeted requirements.' }}</small></div>
</section>

<section class="student-document-grid">
    @forelse($forms as $form)
        @php($submission = $submissions->get($form->id))
        <article class="card student-document-card {{ $requirementsEnforced && $form->is_required ? 'required' : '' }}">
            <div class="student-document-heading">
                <div><span class="document-kind">{{ $form->categoryLabel() }}</span><h3>{{ $form->title }}</h3></div>
                @if($submission)<span class="document-status review-category-badge" style="--review-category-color: {{ $submission->statusColor() }}">{{ $submission->statusLabel() }}</span>@elseif($form->requires_submission)<span class="document-status not-submitted">Not submitted</span>@else<span class="document-status information">Available</span>@endif
            </div>
            @if($form->description)<p class="document-description">{{ $form->description }}</p>@endif
            @if($form->instructions)<div class="document-instructions"><strong>Instructions</strong><p>{{ $form->instructions }}</p></div>@endif
            <dl class="document-meta">
                <div><dt>Audience</dt><dd>{{ $form->component?->code ?? 'All NSTP students' }}</dd></div>
                @if($form->requires_submission)<div><dt>Accepted files</dt><dd>{{ $form->acceptedTypesLabel() }}</dd></div><div><dt>Maximum size</dt><dd>{{ number_format($form->max_size_kb / 1024) }} MB</dd></div><div><dt>Requirement</dt><dd>{{ $requirementsEnforced && $form->is_required ? 'Required' : 'Optional' }}</dd></div>@endif
                @if($form->closes_at)<div><dt>Deadline</dt><dd>{{ $form->closes_at->format('M j, Y g:i A') }}</dd></div>@endif
            </dl>
            @if($form->template_path)<a class="secondary-outline-button document-template-button" href="{{ route('student.documents.template', $form) }}">Download {{ $form->template_original_name ?: 'template' }}</a>@endif
            @if($submission?->review_notes)<div class="document-review-note {{ $submission->status }}"><strong>Reviewer note</strong><p>{{ $submission->review_notes }}</p></div>@endif
            @if($submission)<div class="student-file-row"><span><strong>Uploaded file</strong><small>{{ $submission->original_filename }}</small></span><a class="text-link" href="{{ route('student.documents.submissions.download', $submission) }}">Download</a></div>@endif
            @if($form->requires_submission)
                @if($submission?->isApproved())
                    <p class="document-locked-note">This verified upload is locked. Contact the NSTP Office if it must be changed.</p>
                @else
                    <form method="POST" enctype="multipart/form-data" action="{{ route('student.documents.store', $form) }}" class="student-document-upload">
                        @csrf
                        <label class="field-group"><span>{{ $submission ? 'Replace upload' : 'Upload file' }}</span><input type="file" name="file" accept="{{ collect($form->accepted_extensions)->map(fn($extension) => '.'.$extension)->implode(',') }}" required></label>
                        <button class="primary-button compact" type="submit">{{ $submission ? 'Submit replacement' : 'Submit for review' }}</button>
                    </form>
                @endif
            @endif
        </article>
    @empty
        <section class="card student-documents-empty"><div class="empty-state"><strong>No documents or forms available</strong><span>The NSTP Office has not published a requirement for your component yet.</span></div></section>
    @endforelse
</section>
@endsection
