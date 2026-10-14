@extends('layouts.student')
@section('title', 'Assessments')
@section('page-title', 'Assessments')

@section('page-styles')
    <link rel="stylesheet" href="{{ asset('css/student-assessments.css') }}?v={{ filemtime(public_path('css/student-assessments.css')) }}">
@endsection

@section('content')
<div class="assessment-page">
    <section class="assessment-hero" aria-labelledby="assessment-page-title">
        <div class="assessment-hero-copy">
            <span class="assessment-eyebrow">Learning &amp; assessment</span>
            <h2 id="assessment-page-title">Stay on top of your classwork</h2>
            <p>Review instructions, keep track of deadlines, and open each assessment when you are ready to work.</p>
            @if ($enrollment)
                <div class="assessment-class-context">
                    <span>{{ $enrollment->section?->code ?? 'Assigned section' }}</span>
                    <span aria-hidden="true">•</span>
                    <span>{{ $enrollment->component?->code ?? 'NSTP' }}</span>
                </div>
            @endif
        </div>
        <div class="assessment-hero-mark" aria-hidden="true">
            <svg viewBox="0 0 64 64"><path d="M19 9h26a6 6 0 0 1 6 6v38H13V15a6 6 0 0 1 6-6Z"/><path d="M24 9a8 8 0 0 1 16 0v5H24V9Z"/><path d="m23 31 5 5 12-13M23 44h18"/></svg>
        </div>
    </section>

    <section class="assessment-summary" aria-label="Assessment overview">
        @foreach ([
            ['key' => 'total', 'label' => 'Total assessments', 'icon' => '▦'],
            ['key' => 'to_do', 'label' => 'To do', 'icon' => '○'],
            ['key' => 'turned_in', 'label' => 'Turned in', 'icon' => '↗'],
            ['key' => 'graded', 'label' => 'Graded', 'icon' => '✓'],
        ] as $item)
            <article class="assessment-summary-card summary-{{ $item['key'] }}">
                <span class="assessment-summary-icon" aria-hidden="true">{{ $item['icon'] }}</span>
                <div><strong>{{ $summary[$item['key']] }}</strong><span>{{ $item['label'] }}</span></div>
            </article>
        @endforeach
    </section>

    <section class="assessment-category-panel" aria-labelledby="assessment-category-title">
        <div class="assessment-section-heading">
            <div><span class="assessment-eyebrow">Browse classwork</span><h3 id="assessment-category-title">Filter by category</h3></div>
            @if (filled($filters['type'] ?? null))
                <a class="assessment-clear-filter" href="{{ route('student.assessments.index', request()->except('type', 'page')) }}">Clear filter</a>
            @endif
        </div>
        <nav class="assessment-category-list" aria-label="Assessment categories">
            <a class="assessment-category-chip {{ empty($filters['type']) ? 'is-active' : '' }}" href="{{ route('student.assessments.index', request()->except('type', 'page')) }}" @if (empty($filters['type'])) aria-current="page" @endif>
                <span>All</span><strong>{{ $summary['total'] }}</strong>
            </a>
            @foreach ($assessmentTypes as $type => $label)
                <a class="assessment-category-chip category-{{ $type }} {{ ($filters['type'] ?? null) === $type ? 'is-active' : '' }}" href="{{ route('student.assessments.index', array_merge(request()->except('type', 'page'), ['type' => $type])) }}" @if (($filters['type'] ?? null) === $type) aria-current="page" @endif>
                    <span>{{ $label }}</span><strong>{{ $typeCounts[$type] }}</strong>
                </a>
            @endforeach
        </nav>
    </section>

    <section class="assessment-results" aria-labelledby="assessment-results-title">
        <div class="assessment-results-heading">
            <div>
                <span class="assessment-eyebrow">Your classwork</span>
                <h3 id="assessment-results-title">{{ filled($filters['type'] ?? null) ? $assessmentTypes[$filters['type']] : 'All assessments' }}</h3>
                <p>{{ $assessments->total() }} {{ Str::plural('assessment', $assessments->total()) }} found</p>
            </div>
            @include('pagination.page-size', ['paginator' => $assessments])
        </div>

        <div class="assessment-card-grid">
            @forelse ($assessments as $assessment)
                @php
                    $submission = $assessment->submissions->first();
                    $isGraded = $submission?->score !== null;
                    $isMissing = ! $submission && $assessment->due_at && now()->isAfter($assessment->due_at);
                    $isDueSoon = ! $submission && $assessment->due_at && now()->isBefore($assessment->due_at) && now()->diffInDays($assessment->due_at) <= 3;
                    $status = $isGraded ? 'Graded' : ($submission ? 'Turned in' : ($isMissing ? 'Missing' : 'Assigned'));
                    $score = $isGraded ? rtrim(rtrim(number_format((float) $submission->score, 2), '0'), '.') : null;
                    $maxScore = rtrim(rtrim(number_format((float) $assessment->max_score, 2), '0'), '.');
                    $categoryLabel = $assessment->gradingCategory?->name ?? Str::singular($assessmentTypes[$assessment->type] ?? ucfirst($assessment->type));
                @endphp
                <a class="assessment-card type-{{ $assessment->type }}" href="{{ route('student.assessments.show', $assessment) }}">
                    <span class="assessment-card-accent" aria-hidden="true"></span>
                    <span class="assessment-card-topline">
                        <span class="assessment-type-badge">{{ $categoryLabel }}</span>
                        <span class="assessment-status status-{{ Str::slug($status) }}"><span aria-hidden="true"></span>{{ $status }}</span>
                    </span>
                    <span class="assessment-card-body">
                        <span class="assessment-card-icon" aria-hidden="true">@switch($assessment->type) @case('project') ◆ @break @case('quiz') ? @break @case('exam') ◫ @break @default ✓ @endswitch</span>
                        <span class="assessment-card-copy">
                            <strong>{{ $assessment->title }}</strong>
                            <small>{{ filled($assessment->instructions) ? Str::limit(strip_tags($assessment->instructions), 135) : 'Open this assessment to view the full instructions and submission requirements.' }}</small>
                        </span>
                    </span>
                    <span class="assessment-card-details">
                        <span><small>Deadline</small><strong class="{{ $isMissing ? 'detail-danger' : ($isDueSoon ? 'detail-warning' : '') }}">{{ $assessment->due_at ? $assessment->due_at->format('M d, Y · g:i A') : 'No due date' }}</strong></span>
                        <span><small>{{ $isGraded ? 'Your score' : 'Points' }}</small><strong>{{ $isGraded ? $score.' / '.$maxScore : $maxScore.' pts' }}</strong></span>
                    </span>
                    <span class="assessment-card-footer">
                        <span>{{ $assessment->section->code }} · {{ $assessment->section->component->code }}</span>
                        <strong>Open assessment <span aria-hidden="true">→</span></strong>
                    </span>
                </a>
            @empty
                <div class="assessment-empty-state">
                    <span class="assessment-empty-icon" aria-hidden="true">✓</span>
                    @if (! $enrollment)
                        <strong>No active section yet</strong><p>Your published assessments will appear here after you are enrolled in a section.</p>
                    @elseif (filled($filters['type'] ?? null))
                        <strong>No {{ strtolower($assessmentTypes[$filters['type']]) }} yet</strong><p>There are no published assessments in this category for your section.</p>
                        <a href="{{ route('student.assessments.index', request()->except('type', 'page')) }}">View all assessments</a>
                    @else
                        <strong>No published assessments</strong><p>New activities for your section will appear here once your facilitator publishes them.</p>
                    @endif
                </div>
            @endforelse
        </div>

        @if ($assessments->hasPages())
            <div class="assessment-pagination"><span>Showing {{ $assessments->firstItem() }}–{{ $assessments->lastItem() }} of {{ $assessments->total() }}</span>{{ $assessments->links() }}</div>
        @endif
    </section>
</div>
@endsection
