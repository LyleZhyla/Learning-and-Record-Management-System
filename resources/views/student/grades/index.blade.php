@extends('layouts.student')
@section('title','Grades & Progress') @section('page-title','Grades & Progress')
@section('content')
<div class="page-actions"><div><h2>Your grade records and academic progress</h2><p>Track recorded scores, pending requirements, current standing, and attendance in one private view.</p></div></div>
@if($summary)
<section class="card grade-hero progress-grade-hero">
    <div><span class="eyebrow">Recorded weighted total</span><strong>{{ $summary['grade']===null?'—':number_format($summary['grade'],2) }}</strong><p>{{ $summary['percentage']===null?'No scores recorded yet':number_format($summary['percentage'],2).'% of the full grading sheet' }} · {{ $summary['graded_count'] }} of {{ $summary['total_count'] }} score items graded</p><small class="grade-privacy-note">Private record · visible only to you and authorized NSTP personnel</small></div>
    <div class="progress-hero-standing"><small>Current graded-item standing</small><strong>{{ $summary['current_percentage'] === null ? '—' : number_format($summary['current_percentage'], 2).'%' }}</strong><span class="progress-status {{ $summary['overall_status'] }}">{{ $summary['overall_label'] }}</span></div>
</section>

<section class="progress-metric-grid student-progress-metrics" aria-label="Student progress summary">
    <article class="progress-metric"><span>Grade encoding</span><strong>{{ number_format($summary['completion_percentage'], 0) }}%</strong><small>{{ $summary['pending_count'] }} score item(s) pending</small></article>
    <article class="progress-metric"><span>Attendance</span><strong>{{ $summary['attendance_rate'] === null ? '—' : number_format($summary['attendance_rate'], 1).'%' }}</strong><small>{{ $summary['attended_sessions'] }} of {{ $summary['attendance_sessions'] }} completed sessions</small></article>
    <article class="progress-metric"><span>Awaiting grading</span><strong>{{ $summary['awaiting_grading_count'] }}</strong><small>Submitted work without a score</small></article>
    <article class="progress-metric attention"><span>Missing requirements</span><strong>{{ $summary['missing_count'] }}</strong><small>Past due without submission</small></article>
</section>

@if($summary['attention_items']->isNotEmpty())
<section class="card progress-attention-card"><div><span aria-hidden="true">!</span></div><div><strong>Items that need attention</strong><ul>@foreach($summary['attention_items'] as $item)<li>{{ $item }}</li>@endforeach</ul></div></section>
@else
<section class="card progress-attention-card clear"><div><span aria-hidden="true">✓</span></div><div><strong>No urgent progress issues detected</strong><p>Continue completing requirements and attending scheduled sessions.</p></div></section>
@endif

@foreach($summary['categories'] as $categoryItem)
<section class="card user-table-card student-grade-category">
    <div class="sectioning-toolbar category-progress-heading"><div><h3>{{ $categoryItem['category']->name }} · {{ number_format($categoryItem['category']->weight,2) }}%</h3><p class="muted-cell">{{ number_format($categoryItem['earned'],2) }} ÷ {{ number_format($categoryItem['maximum'],2) }} × {{ number_format($categoryItem['category']->weight,2) }}% = <strong>{{ number_format($categoryItem['weighted_score'],2) }} recorded weighted points</strong></p></div><div><strong>{{ number_format($categoryItem['completion_percentage'], 0) }}%</strong><small>graded</small></div></div>
    <div class="progress-track" aria-label="{{ $categoryItem['category']->name }} grading progress"><span style="width:{{ min(100, $categoryItem['completion_percentage']) }}%;--progress-color:{{ $categoryItem['category']->color }}"></span></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Score item</th><th>Due date</th><th>Submission status</th><th>Score</th><th>Item percentage</th><th>Feedback</th></tr></thead><tbody>
    @forelse($categoryItem['category']->assessments as $assessment) @php($submission=$assessment->submissions->first())
    <tr><td><strong>{{ $assessment->title }}</strong><br><small class="muted-cell">{{ ucfirst($assessment->type) }}</small></td><td>{{ $assessment->due_at?->format('M d, Y g:i A') ?? 'No deadline' }}</td><td>@if($submission?->score !== null)<span class="progress-status completed">Graded</span>@elseif($submission?->submitted_at)<span class="progress-status on_track">Submitted</span>@elseif($assessment->due_at?->isPast())<span class="progress-status at_risk">Missing</span>@else<span class="progress-status not_started">Pending</span>@endif</td><td>{{ $submission?->score===null?'Pending':number_format($submission->score,2).' / '.number_format($assessment->max_score,2) }}</td><td>{{ $submission?->score===null?'—':number_format(($submission->score/$assessment->max_score)*100,2).'%' }}</td><td>{{ $submission?->feedback ?? '—' }}</td></tr>
    @empty<tr><td colspan="6" class="muted-cell">No score items in this category yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endforeach
@else<section class="card empty-state"><strong>No grade or progress summary available</strong><span>Your enrollment or published assessments are not available yet.</span></section>@endif
@endsection
