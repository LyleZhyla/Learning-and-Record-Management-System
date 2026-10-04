@extends('layouts.coordinator')
@section('title', 'Progress Monitoring')
@section('page-title', 'Grades & Progress Monitoring')
@section('content')
<div class="page-actions"><div><h2>Student progress monitoring</h2><p>Review current standing, grade completion, missing requirements, and attendance signals for your assigned component.</p></div></div>
<section class="card term-panel">
    <form method="GET" class="term-form progress-filter-form">
        <label class="field-group"><span>Section</span><select name="section_id">@foreach($sections as $item)<option value="{{ $item->id }}" @selected($section?->id===$item->id)>{{ $item->code }} · {{ $item->component->code }} · {{ $item->academic_year }}</option>@endforeach</select></label>
        <label class="field-group"><span>Student search</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or email"></label>
        <label class="field-group"><span>Progress status</span><select name="progress_status"><option value="">All statuses</option><option value="on_track" @selected(($filters['progress_status'] ?? '')==='on_track')>On track</option><option value="at_risk" @selected(($filters['progress_status'] ?? '')==='at_risk')>Needs attention</option><option value="completed" @selected(($filters['progress_status'] ?? '')==='completed')>Completed</option><option value="needs_improvement" @selected(($filters['progress_status'] ?? '')==='needs_improvement')>Needs improvement</option><option value="not_started" @selected(($filters['progress_status'] ?? '')==='not_started')>Not started</option></select></label>
        <button class="filter-button">Apply filters</button>
    </form>
</section>
@if($section)
<section class="card assessment-summary progress-section-summary">
    <div><span class="eyebrow">Selected section</span><h2>{{ $section->code }}</h2><p>{{ $section->component->code }} · {{ $section->semesterLabel() }} {{ $section->academic_year }}</p></div>
    <div><strong>{{ $totalStudents }}</strong><small>Students</small></div><div><strong>{{ $onTrackStudents }}</strong><small>On track</small></div><div><strong>{{ $atRiskStudents }}</strong><small>Need attention</small></div><div><strong>{{ $completedStudents }}</strong><small>Completed</small></div>
</section>
<section class="progress-overview-grid"><article class="card"><small>Average current standing</small><strong>{{ $averageStanding === null ? '—' : number_format($averageStanding, 1).'%' }}</strong></article><article class="card"><small>Average attendance</small><strong>{{ $averageAttendance === null ? '—' : number_format($averageAttendance, 1).'%' }}</strong></article><article class="card"><small>Students with a computed grade</small><strong>{{ $gradedStudents }} / {{ $totalStudents }}</strong></article></section>
@endif
<section class="card user-table-card">
    <div class="table-wrap"><table class="data-table progress-monitoring-table">
        <thead><tr><th>Student</th><th>Progress</th><th>Current standing</th><th>Recorded weighted</th><th>Computed grade</th><th>Attendance</th><th>Missing</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($summaries as $item)
                <tr><td><strong>{{ $item['student']->name }}</strong><br><small class="muted-cell">{{ $item['student']->email }}</small></td><td><div class="compact-progress"><span><i style="width:{{ min(100,$item['completion_percentage']) }}%"></i></span><small>{{ $item['graded_count'] }} / {{ $item['total_count'] }} graded</small></div></td><td>{{ $item['current_percentage']===null?'—':number_format($item['current_percentage'],2).'%' }}</td><td>{{ $item['percentage']===null?'—':number_format($item['percentage'],2).'%' }}</td><td><strong class="grade-number">{{ $item['grade']===null?'—':number_format($item['grade'],2) }}</strong></td><td>{{ $item['attendance_rate']===null?'—':number_format($item['attendance_rate'],1).'%' }}<br><small class="muted-cell">{{ $item['attended_sessions'] }}/{{ $item['attendance_sessions'] }} sessions</small></td><td>{{ $item['missing_count'] }}</td><td><span class="progress-status {{ $item['overall_status'] }}">{{ $item['overall_label'] }}</span>@if($item['attention_items']->isNotEmpty())<small class="progress-reason">{{ $item['attention_items']->first() }}</small>@endif</td></tr>
            @empty
                <tr><td colspan="8"><div class="empty-state"><strong>No matching progress records</strong><span>Change the section, student search, or progress-status filter.</span></div></td></tr>
            @endforelse
        </tbody>
    </table></div>
    @if($summaries->hasPages())<div class="pagination-row"><span>Showing {{ $summaries->firstItem() }}–{{ $summaries->lastItem() }} of {{ $summaries->total() }}</span>{{ $summaries->links() }}</div>@endif
</section>
@endsection
