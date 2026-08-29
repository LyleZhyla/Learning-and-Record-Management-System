@extends('layouts.student')
@section('title','Grades') @section('page-title','Grade Summary')
@section('content')
<div class="page-actions"><div><h2>Your performance</h2><p>Your final grade uses the configured 1.00–5.00 transmutation scale.</p></div></div>
@if($summary)
<section class="card grade-hero"><div><span class="eyebrow">Current computed grade</span><strong>{{ $summary['grade']===null?'—':number_format($summary['grade'],2) }}</strong><p>{{ $summary['percentage']===null?'No scores recorded yet':number_format($summary['percentage'],2).'% weighted total' }} · {{ $summary['graded_count'] }} of {{ $summary['total_count'] }} score items graded</p></div></section>
@foreach($summary['categories'] as $categoryItem)
<section class="card user-table-card student-grade-category">
    <div class="sectioning-toolbar"><div><h3>{{ $categoryItem['category']->name }} · {{ number_format($categoryItem['category']->weight,2) }}%</h3><p class="muted-cell">{{ number_format($categoryItem['earned'],2) }} / {{ number_format($categoryItem['maximum'],2) }} raw points · {{ number_format($categoryItem['weighted_score'],2) }} weighted points</p></div></div>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Score item</th><th>Score</th><th>Item percentage</th><th>Feedback</th></tr></thead><tbody>
    @forelse($categoryItem['category']->assessments as $assessment) @php($submission=$assessment->submissions->first())
    <tr><td><strong>{{ $assessment->title }}</strong><br><small class="muted-cell">{{ ucfirst($assessment->type) }}</small></td><td>{{ $submission?->score===null?'Pending':number_format($submission->score,2).' / '.number_format($assessment->max_score,2) }}</td><td>{{ $submission?->score===null?'—':number_format(($submission->score/$assessment->max_score)*100,2).'%' }}</td><td>{{ $submission?->feedback ?? '—' }}</td></tr>
    @empty<tr><td colspan="4" class="muted-cell">No score items in this category yet.</td></tr>@endforelse
    </tbody></table></div>
</section>
@endforeach
@else<section class="card empty-state"><strong>No grade summary available</strong><span>Your enrollment or published assessments are not available yet.</span></section>@endif
@endsection
