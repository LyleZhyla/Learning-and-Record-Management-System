@extends($layout)
@section('title', $assessment->title)
@section('page-title', 'Review Assessment')

@section('content')
<link rel="stylesheet" href="{{ asset('css/ai-assessment-scoring.css') }}?v={{ filemtime(public_path('css/ai-assessment-scoring.css')) }}">
@php
    $submittedCount = $assessment->submissions->filter(fn($submission) => filled($submission->answer_text) || filled($submission->file_path) || $submission->submitted_at)->count();
    $gradedCount = $assessment->submissions->whereNotNull('score')->count();
    $pendingReviewCount = max(0, $submittedCount - $gradedCount);
@endphp

<div class="assessment-review-page">
    <a class="review-back-link" href="{{ route($routePrefix.'.assessments.index') }}"><span aria-hidden="true">←</span> Back to assessments</a>

    <section class="assessment-review-hero">
        <div class="assessment-hero-copy">
            <div class="assessment-hero-tags">
                <span>{{ ucfirst($assessment->type) }}</span>
                <span class="status">{{ ucfirst($assessment->status) }}</span>
                <span>{{ $assessment->section->component->code }} · {{ $assessment->section->code }}</span>
            </div>
            <h2>{{ $assessment->title }}</h2>
            <p>{{ $assessment->instructions ?: 'No additional assessment instructions were provided.' }}</p>
        </div>
        <div class="assessment-hero-metrics" aria-label="Assessment review summary">
            <article><span>Maximum score</span><strong>{{ number_format($assessment->max_score, 2) }}</strong><small>points</small></article>
            <article><span>Submitted</span><strong>{{ $submittedCount }}</strong><small>of {{ $students->total() }} students</small></article>
            <article class="{{ $pendingReviewCount > 0 ? 'attention' : 'complete' }}"><span>Awaiting review</span><strong>{{ $pendingReviewCount }}</strong><small>{{ $gradedCount }} already graded</small></article>
        </div>
    </section>

    <section class="assessment-workflow-note">
        <div class="workflow-note-icon" aria-hidden="true">✓</div>
        <div class="workflow-note-copy"><strong>Your review determines the official score</strong><p>Manual scores and approved AI suggestions are recorded under <b>{{ $assessment->gradingCategory?->name ?? 'the selected category' }}</b>. AI suggestions never become official scores until a facilitator reviews and approves them.</p></div>
        <div class="review-flow-steps" aria-label="Review process"><span><b>1</b> Open work</span><span><b>2</b> Review evidence</span><span><b>3</b> Save score</span></div>
    </section>

@if(auth()->user()->isFacilitator())
<section class="card ai-rubric-card assessment-rubric-panel">
    <div class="card-heading">
        <div><span class="eyebrow">AI-assisted scoring</span><h3>Official scoring rubric</h3><p>The AI evaluates only against this rubric. Include criteria, point allocations, and what earns full or partial credit.</p></div>
        <span class="status-badge {{ filled(config('services.openai.api_key')) ? 'active' : 'inactive' }}"><i></i>{{ filled(config('services.openai.api_key')) ? 'AI ready' : 'Needs API key' }}</span>
    </div>
    <form method="POST" action="{{ route($routePrefix.'.assessments.rubric.update', $assessment) }}">
        @csrf
        @method('PUT')
        @include('learning.assessments._rubric-builder', [
            'rubricCriteria' => $assessment->rubricCriteria(),
            'rubricRequired' => true,
            'rubricMaxScore' => (float) $assessment->max_score,
            'rubricContext' => ['title' => $assessment->title, 'type' => $assessment->type, 'instructions' => $assessment->instructions, 'max_score' => (float) $assessment->max_score],
        ])
        <div class="form-actions"><button class="secondary-outline-button" type="submit">Save rubric</button></div>
    </form>
</section>
@elseif(auth()->user()->isCoordinator())
<section class="card ai-rubric-card ai-readonly-card assessment-rubric-panel">
    <div class="card-heading">
        <div><span class="eyebrow">View only</span><h3>Official AI scoring rubric</h3><p>The coordinator can monitor the rubric and AI-assisted scoring results but cannot change or approve them.</p></div>
    </div>
    <div class="ai-readonly-rubric">
        @forelse($assessment->rubricCriteria() as $criterion)
            <article class="readonly-rubric-criterion"><div><strong>{{ $criterion['title'] }}</strong><b>{{ number_format((float) $criterion['percentage'], 2) }}% · {{ number_format((float) $criterion['score'], 2) }} pts</b></div><p>{{ $criterion['description'] }}</p></article>
        @empty
            No AI scoring rubric has been provided by the facilitator.
        @endforelse
    </div>
</section>
@endif

<section class="assessment-submissions-panel">
    <div class="assessment-submissions-heading">
        <div><span class="eyebrow">Review queue</span><h3>Student scores and submissions</h3><p>Open each submitted response, check the evidence, and save the official score.</p></div>
        <div class="submission-summary-pills"><span class="pending">{{ $pendingReviewCount }} pending</span><span class="graded">{{ $gradedCount }} graded</span></div>
    </div>

    <div class="assessment-review-list">
        @forelse($students as $enrollment)
            @php
                $submission = $assessment->submissions->firstWhere('student_id', $enrollment->student_id);
                $hasWork = $submission && (filled($submission->answer_text) || filled($submission->file_path));
                $isGraded = $submission?->score !== null;
                $studentInitials = collect(explode(' ', $enrollment->student->name))->filter()->take(2)->map(fn($part) => mb_substr($part, 0, 1))->implode('');
            @endphp
            <article class="assessment-review-card {{ $isGraded ? 'graded' : ($hasWork ? 'pending' : 'missing') }}">
                <header class="assessment-student-header">
                    <div class="assessment-student-profile">
                        <span class="assessment-student-avatar">{{ strtoupper($studentInitials) }}</span>
                        <div><h4>{{ $enrollment->student->name }}</h4><p>{{ $enrollment->student->email }}</p></div>
                    </div>
                    <span class="submission-state {{ $isGraded ? 'graded' : ($hasWork ? 'pending' : 'missing') }}"><i></i>{{ $isGraded ? 'Graded' : ($hasWork ? 'Awaiting review' : 'Not submitted') }}</span>
                </header>

                <div class="assessment-student-content">
                    <div class="submission-overview">
                        <span class="review-column-label">Submitted work</span>
                        @if($hasWork)
                            <div class="submission-type-list">
                                @if(filled($submission->answer_text))<span><b aria-hidden="true">T</b> Written response</span>@endif
                                @if(filled($submission->original_filename))<span><b aria-hidden="true">F</b> {{ $submission->original_filename }}</span>@endif
                            </div>
                            @if(filled($submission->answer_text))<p class="submission-answer-excerpt">{{ Str::limit($submission->answer_text, 170) }}</p>@endif
                        @elseif($isGraded)
                            <p class="submission-empty-copy">Score encoded by staff without an online submission.</p>
                        @else
                            <p class="submission-empty-copy">No response or attachment has been submitted.</p>
                        @endif
                    </div>

                    <div class="submission-timeline">
                        <span class="review-column-label">Submission date</span>
                        <strong>{{ $submission?->submitted_at?->format('M d, Y') ?? '—' }}</strong>
                        <small>{{ $submission?->submitted_at?->format('g:i A') ?? 'No submission yet' }}</small>
                    </div>

                    <div class="submission-score-summary">
                        <span class="review-column-label">Official score</span>
                        <strong>{{ $isGraded ? number_format((float) $submission->score, 2) : 'Pending' }}</strong>
                        <small>{{ $isGraded ? 'out of '.number_format((float) $assessment->max_score, 2) : number_format((float) $assessment->max_score, 2).' points available' }}</small>
                    </div>

                    <div class="submission-review-action">
                        @if(auth()->user()->isFacilitator() && $hasWork)
                            <button class="submission-review-primary" type="button" data-submission-open="submission-review-{{ $submission->id }}">
                                <span aria-hidden="true">▤</span>
                                <span><strong>View student work</strong><small>{{ $isGraded ? 'Review scored work' : 'Review & score submission' }}</small></span>
                                <b aria-hidden="true">→</b>
                            </button>
                            @include('learning.assessments._submission-review-dialog', compact('submission', 'enrollment'))
                        @elseif(auth()->user()->isCoordinator())
                            <span class="coordinator-view-label">Coordinator view only</span>
                        @endif
                    </div>
                </div>

                @if(auth()->user()->isCoordinator())
                    <div class="coordinator-score-review">
                        <div class="ai-score-workspace ai-score-readonly">
                            @if($submission?->ai_generated_at)
                                @php($needsReview = ($submission->ai_breakdown['needs_manual_review'] ?? false) || (float) $submission->ai_confidence < 70)
                                <details class="ai-score-suggestion">
                                    <summary><span><strong>AI suggested {{ number_format((float) $submission->ai_suggested_score, 2) }} / {{ number_format((float) $assessment->max_score, 2) }}</strong><small>{{ number_format((float) $submission->ai_confidence, 0) }}% confidence</small></span><span class="status-badge {{ $needsReview ? 'inactive' : 'active' }}"><i></i>{{ $needsReview ? 'Manual review required' : 'Reviewed by AI' }}</span></summary>
                                    <div class="ai-criteria-list">@foreach(($submission->ai_breakdown['criteria'] ?? []) as $criterion)<article><div><strong>{{ $criterion['criterion'] }}</strong><b>{{ number_format((float) $criterion['points_awarded'], 2) }} / {{ number_format((float) $criterion['points_possible'], 2) }}</b></div><p>{{ $criterion['evidence'] }}</p></article>@endforeach</div>
                                    <div class="ai-readonly-feedback"><strong>Suggested feedback</strong><p>{{ $submission->ai_feedback ?: 'No suggested feedback.' }}</p></div>
                                </details>
                            @else
                                <p class="ai-score-help">No AI score suggestion has been generated for this submission.</p>
                            @endif
                        </div>
                        <div class="official-score-readonly"><strong>Official feedback</strong><span>{{ $submission?->score === null ? 'Pending' : number_format((float) $submission->score, 2).' / '.number_format((float) $assessment->max_score, 2) }}</span><p>{{ $submission?->feedback ?: 'No official feedback yet.' }}</p></div>
                    </div>
                @elseif(!auth()->user()->isFacilitator() || !$submission || (blank($submission->answer_text) && blank($submission->file_path)))
                    <form class="grade-form assessment-inline-grade-form" method="POST" action="{{ route($routePrefix.'.assessments.score', [$assessment, $enrollment->student]) }}">
                        @csrf @method('PUT')
                        <label><span>Manual score</span><input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}" name="score" value="{{ $submission?->score }}" placeholder="0.00" required></label>
                        <label><span>Feedback <small>Optional</small></span><input name="feedback" value="{{ $submission?->feedback }}" placeholder="Add feedback for the student"></label>
                        <button class="filter-button">Save manual score</button>
                    </form>
                @endif

                @if($submission?->ai_approved_at)
                    <div class="ai-approved-note">✓ AI-assisted score approved by {{ $submission->aiApprover?->name ?? 'a facilitator' }} {{ $submission->ai_approved_at->diffForHumans() }}.</div>
                @endif
            </article>
        @empty
            <article class="assessment-review-empty"><span aria-hidden="true">👥</span><h4>No students enrolled</h4><p>Students will appear here after they are enrolled in this section.</p></article>
        @endforelse
    </div>

    @if($students->total() > 0)<div class="pagination-row assessment-review-pagination"><span>Showing {{ $students->firstItem() }}–{{ $students->lastItem() }} of {{ $students->total() }} students</span>{{ $students->links() }}</div>@endif
</section>
</div>
<script src="{{ asset('js/rubric-builder.js') }}?v={{ filemtime(public_path('js/rubric-builder.js')) }}"></script>
<script src="{{ asset('js/submission-review-modal.js') }}?v={{ filemtime(public_path('js/submission-review-modal.js')) }}"></script>
@endsection
