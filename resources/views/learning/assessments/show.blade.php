@extends($layout)
@section('title', $assessment->title)
@section('page-title', 'Review Assessment')

@section('content')
<link rel="stylesheet" href="{{ asset('css/ai-assessment-scoring.css') }}?v={{ filemtime(public_path('css/ai-assessment-scoring.css')) }}">
<div class="back-row"><a href="{{ route($routePrefix.'.assessments.index') }}">← Back to assessments</a></div>

<section class="card assessment-summary">
    <div>
        <span class="eyebrow">{{ ucfirst($assessment->type) }} · {{ ucfirst($assessment->status) }}</span>
        <h2>{{ $assessment->title }}</h2>
        <p>{{ $assessment->section->code }} · {{ $assessment->section->component->code }}</p>
    </div>
    <div><strong>{{ number_format($assessment->max_score, 2) }}</strong><small>Maximum points</small></div>
    <div><strong>{{ $assessment->gradingCategory?->name ?? 'Uncategorized' }}</strong><small>{{ $assessment->gradingCategory ? number_format($assessment->gradingCategory->weight, 2).'% category weight' : 'Grading category' }}</small></div>
</section>

<section class="information-strip">
    <span>✓</span>
    <div><strong>Automatic grading-sheet encoding with facilitator approval</strong><p>Manual scores and approved AI suggestions are automatically recorded under <b>{{ $assessment->gradingCategory?->name ?? 'the selected category' }}</b>. AI suggestions never become official scores until a facilitator reviews and approves them.</p></div>
</section>

@if(auth()->user()->isFacilitator())
<section class="card ai-rubric-card">
    <div class="card-heading">
        <div><span class="eyebrow">AI-assisted scoring</span><h3>Official scoring rubric</h3><p>The AI evaluates only against this rubric. Include criteria, point allocations, and what earns full or partial credit.</p></div>
        <span class="status-badge {{ filled(config('services.openai.api_key')) ? 'active' : 'inactive' }}"><i></i>{{ filled(config('services.openai.api_key')) ? 'AI ready' : 'Needs API key' }}</span>
    </div>
    <form method="POST" action="{{ route($routePrefix.'.assessments.rubric.update', $assessment) }}">
        @csrf
        @method('PUT')
        <label class="field-group"><span>Rubric</span><textarea name="rubric" rows="7" required placeholder="Accuracy — 40 points...">{{ old('rubric', $assessment->rubric) }}</textarea></label>
        <div class="form-actions"><button class="secondary-outline-button" type="submit">Save rubric</button></div>
    </form>
</section>
@endif

<section class="card user-table-card">
    <div class="sectioning-toolbar"><div><h3>Student scores and submissions</h3><p class="muted-cell">{{ $assessment->instructions ?: 'No additional instructions.' }}</p></div></div>
    <div class="table-wrap">
        <table class="data-table assessment-review-table">
            <thead><tr><th>Student</th><th>Submitted work</th><th>Submitted</th><th>Score and feedback</th></tr></thead>
            <tbody>
            @forelse($students as $enrollment)
                @php($submission = $assessment->submissions->firstWhere('student_id', $enrollment->student_id))
                <tr>
                    <td>{{ $enrollment->student->name }}<br><small class="muted-cell">{{ $enrollment->student->email }}</small></td>
                    <td>
                        @if(filled($submission?->answer_text) || filled($submission?->original_filename))
                            @if(filled($submission?->answer_text))<span>{{ Str::limit($submission->answer_text, 140) }}</span>@endif
                            @if(filled($submission?->original_filename))<small class="submission-file-name">Attachment: {{ $submission->original_filename }}</small>@endif
                        @elseif($submission?->score !== null)
                            <span class="muted-cell">Score encoded by staff</span>
                        @else
                            <span class="muted-cell">Not submitted</span>
                        @endif
                    </td>
                    <td>{{ $submission?->submitted_at?->format('M d, Y g:i A') ?? '—' }}</td>
                    <td>
                        @if(auth()->user()->isFacilitator() && $submission && (filled($submission->answer_text) || filled($submission->file_path)))
                            <div class="ai-score-workspace">
                                <form method="POST" action="{{ route($routePrefix.'.assessments.ai-score.generate', [$assessment, $submission]) }}">
                                    @csrf
                                    <button class="ai-score-button" type="submit" @disabled(blank($assessment->rubric) || blank(config('services.openai.api_key')))>{{ $submission->ai_generated_at ? 'Regenerate AI suggestion' : 'Generate AI suggestion' }}</button>
                                </form>

                                @if($submission->ai_generated_at)
                                    @php($needsReview = ($submission->ai_breakdown['needs_manual_review'] ?? false) || (float) $submission->ai_confidence < 70)
                                    <details class="ai-score-suggestion" open>
                                        <summary>
                                            <span><strong>Suggested {{ number_format((float) $submission->ai_suggested_score, 2) }} / {{ number_format((float) $assessment->max_score, 2) }}</strong><small>{{ number_format((float) $submission->ai_confidence, 0) }}% confidence</small></span>
                                            <span class="status-badge {{ $needsReview ? 'inactive' : 'active' }}"><i></i>{{ $needsReview ? 'Manual review required' : 'Ready for review' }}</span>
                                        </summary>
                                        <div class="ai-criteria-list">
                                            @foreach(($submission->ai_breakdown['criteria'] ?? []) as $criterion)
                                                <article><div><strong>{{ $criterion['criterion'] }}</strong><b>{{ number_format((float) $criterion['points_awarded'], 2) }} / {{ number_format((float) $criterion['points_possible'], 2) }}</b></div><p>{{ $criterion['evidence'] }}</p></article>
                                            @endforeach
                                        </div>
                                        <form class="ai-approval-form" method="POST" action="{{ route($routePrefix.'.assessments.ai-score.approve', [$assessment, $submission]) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="suggestion_generated_at" value="{{ $submission->ai_generated_at->toIso8601String() }}">
                                            <label><span>Reviewed score</span><input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}" name="score" value="{{ $submission->ai_suggested_score }}" required></label>
                                            <label><span>Reviewed feedback</span><textarea name="feedback" rows="4">{{ $submission->ai_feedback }}</textarea></label>
                                            <small>Generated {{ $submission->ai_generated_at->diffForHumans() }}. You may edit the score and feedback before approval.</small>
                                            <button class="primary-button compact" type="submit">Approve as official score</button>
                                        </form>
                                    </details>
                                @elseif(blank($assessment->rubric))
                                    <small class="ai-score-help">Save a rubric above to enable AI scoring.</small>
                                @elseif(blank(config('services.openai.api_key')))
                                    <small class="ai-score-help">Configure OPENAI_API_KEY to enable AI scoring.</small>
                                @endif
                            </div>
                        @endif

                        <form class="grade-form" method="POST" action="{{ route($routePrefix.'.assessments.score', [$assessment, $enrollment->student]) }}">
                            @csrf
                            @method('PUT')
                            <input type="number" step="0.01" min="0" max="{{ $assessment->max_score }}" name="score" value="{{ $submission?->score }}" placeholder="Score" required>
                            <input name="feedback" value="{{ $submission?->feedback }}" placeholder="Feedback (optional)">
                            <button class="filter-button">Save manual score</button>
                        </form>
                        @if($submission?->ai_approved_at)
                            <small class="ai-approved-note">AI-assisted score approved by {{ $submission->aiApprover?->name ?? 'a facilitator' }} {{ $submission->ai_approved_at->diffForHumans() }}.</small>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No students are enrolled in this section.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
