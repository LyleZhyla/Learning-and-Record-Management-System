@extends('layouts.student')
@section('title', $assessment->title)
@section('page-title', 'Assessment')

@section('content')
@php
    $isLate = $assessment->due_at && now()->isAfter($assessment->due_at);
    $workStatus = $submission
        ? ($submission->score !== null ? 'Graded' : 'Turned in')
        : ($isLate ? 'Missing' : 'Assigned');
@endphp

<div class="back-row classroom-back"><a href="{{ route('student.assessments.index') }}">← Classwork</a></div>

<div class="classroom-assignment-layout">
    <main class="classroom-assignment-main">
        <section class="classroom-assignment-header">
            <div class="classroom-title-row">
                <span class="classroom-assignment-icon large" aria-hidden="true">✓</span>
                <div class="classroom-title-copy">
                    <span class="eyebrow">{{ ucfirst($assessment->type) }}</span>
                    <h2>{{ $assessment->title }}</h2>
                    <p>{{ $assessment->section->component->code }} · {{ $assessment->section->code }}</p>
                </div>
                <div class="classroom-points">
                    <strong>{{ number_format($assessment->max_score, 2) }}</strong>
                    <span>points</span>
                </div>
            </div>

            <div class="classroom-assignment-meta">
                <span>Posted {{ $assessment->published_at?->format('M d, Y') ?? 'recently' }}</span>
                <span class="{{ $isLate && ! $submission ? 'deadline' : '' }}">
                    {{ $assessment->due_at ? 'Due '.$assessment->due_at->format('M d, Y · g:i A') : 'No due date' }}
                </span>
            </div>
        </section>

        <section class="classroom-instructions" aria-labelledby="instructions-heading">
            <h3 id="instructions-heading">Instructions</h3>
            <div>{!! nl2br(e($assessment->instructions ?: 'No additional instructions were provided.')) !!}</div>
        </section>

        @if ($submission?->score !== null)
            <section class="classroom-feedback-card">
                <div>
                    <span class="eyebrow">Facilitator feedback</span>
                    <h3>{{ number_format($submission->score, 2) }} / {{ number_format($assessment->max_score, 2) }}</h3>
                </div>
                <p>{{ $submission->feedback ?: 'No written feedback was provided.' }}</p>
            </section>
        @endif
    </main>

    <aside class="classroom-work-card">
        <div class="classroom-work-heading">
            <h3>Your work</h3>
            <span class="classroom-work-state state-{{ Str::slug($workStatus) }}">{{ $workStatus }}</span>
        </div>

        <form method="POST" enctype="multipart/form-data" action="{{ route('student.assessments.submit', $assessment) }}">
            @csrf

            @if ($submission?->original_filename)
                <div class="classroom-current-file">
                    <span class="classroom-file-icon" aria-hidden="true">▤</span>
                    <span><strong>{{ $submission->original_filename }}</strong><small>Current attachment</small></span>
                </div>
            @endif

            <label class="classroom-file-picker">
                <span class="classroom-add-icon" aria-hidden="true">＋</span>
                <span><strong>Add an attachment</strong><small data-assessment-file-name>PDF, Office document, image, or text · Max 10 MB</small></span>
                <input type="file" name="file" data-assessment-file accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.jpg,.jpeg,.png">
            </label>

            <label class="classroom-response-field">
                <span>Written response <small>(optional with attachment)</small></span>
                <textarea name="answer_text" rows="6" placeholder="Type your response here...">{{ old('answer_text', $submission?->answer_text) }}</textarea>
            </label>

            <button class="primary-button classroom-turn-in" type="submit">
                {{ $submission ? 'Resubmit' : 'Turn in' }}
            </button>
        </form>

        @if ($submission)
            <p class="classroom-submitted-time">Last submitted {{ $submission->submitted_at->format('M d, Y · g:i A') }}</p>
        @else
            <p class="classroom-submit-note">Attach a file, enter a response, or provide both before turning in.</p>
        @endif
    </aside>
</div>
<script src="{{ asset('js/assessment-upload.js') }}"></script>
@endsection
