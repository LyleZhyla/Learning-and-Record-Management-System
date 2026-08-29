@extends('layouts.student')
@section('title', 'Assessments')
@section('page-title', 'Assessments')

@section('content')
<div class="page-actions classroom-page-heading">
    <div>
        <span class="eyebrow">Classwork</span>
        <h2>Your assessments</h2>
        <p>Open an activity to review its instructions, attach your work, and monitor its grading status.</p>
    </div>
</div>

<section class="classroom-stream" aria-label="Published assessments">
    @forelse ($assessments as $assessment)
        @php
            $submission = $assessment->submissions->first();
            $isLate = $assessment->due_at && now()->isAfter($assessment->due_at) && ! $submission;
            $status = $submission
                ? ($submission->score !== null ? 'Graded' : 'Turned in')
                : ($isLate ? 'Missing' : 'Assigned');
        @endphp
        <a class="classroom-stream-item" href="{{ route('student.assessments.show', $assessment) }}">
            <span class="classroom-assignment-icon" aria-hidden="true">✓</span>
            <span class="classroom-stream-content">
                <strong>{{ $assessment->title }}</strong>
                <small>{{ $assessment->section->code }} · {{ ucfirst($assessment->type) }}</small>
            </span>
            <span class="classroom-stream-meta">
                <span class="classroom-work-state state-{{ Str::slug($status) }}">{{ $status }}</span>
                <small>{{ $assessment->due_at ? 'Due '.$assessment->due_at->format('M d, g:i A') : 'No due date' }}</small>
            </span>
            <span class="classroom-chevron" aria-hidden="true">›</span>
        </a>
    @empty
        <div class="card empty-state classroom-empty">
            <span class="classroom-assignment-icon" aria-hidden="true">✓</span>
            <strong>No published assessments</strong>
            <span>New activities for your section will appear here.</span>
        </div>
    @endforelse
</section>

@if ($assessments->hasPages())
    <div class="pagination-row classroom-pagination">
        <span>Showing {{ $assessments->count() }} of {{ $assessments->total() }}</span>
        {{ $assessments->links() }}
    </div>
@endif
@endsection
