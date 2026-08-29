@extends('layouts.coordinator')
@section('title', 'Account Details') @section('page-title', 'Component Account Details')
@section('content')
<div class="back-row"><a href="{{ route('coordinator.accounts.index') }}">← Back to facilitators and students</a></div>

<section class="card student-record-header">
    <div><span class="eyebrow">{{ $user->roleLabel() }} · {{ auth()->user()->nstpComponent?->code }}</span><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div>
    <div><small>Status</small><strong>{{ $user->statusLabel() }}</strong></div><div><small>Registered</small><strong>{{ $user->created_at?->format('M d, Y') }}</strong></div>
</section>

@if($user->isFacilitator())
<section class="card user-table-card student-data-section"><div class="sectioning-toolbar"><div><span class="eyebrow">Assigned component only</span><h3>Handled sections</h3></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Component</th><th>Section</th><th>Academic year</th><th>Semester</th><th>Status</th></tr></thead><tbody>@forelse($user->facilitatedSections as $section)<tr><td>{{ $section->component?->code ?? '—' }}</td><td>{{ $section->code }}</td><td>{{ $section->academic_year }}</td><td>{{ $section->semesterLabel() }}</td><td>{{ ucfirst($section->status) }}</td></tr>@empty<tr><td colspan="5" class="muted-cell">No section in your assigned component.</td></tr>@endforelse</tbody></table></div></section>
@else
<section class="card user-table-card student-data-section"><div class="sectioning-toolbar"><div><span class="eyebrow">Assigned component only</span><h3>Enrollment history</h3></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Component</th><th>Section</th><th>Academic year</th><th>Semester</th><th>Status</th></tr></thead><tbody>@forelse($user->nstpEnrollments as $enrollment)<tr><td>{{ $enrollment->component?->code ?? '—' }}</td><td>{{ $enrollment->section?->code ?? 'Unassigned' }}</td><td>{{ $enrollment->academic_year }}</td><td>{{ $enrollment->section?->semesterLabel() ?? ucfirst($enrollment->semester) }}</td><td>{{ ucfirst($enrollment->status) }}</td></tr>@empty<tr><td colspan="5" class="muted-cell">No enrollment record in this component.</td></tr>@endforelse</tbody></table></div></section>

<section class="card user-table-card student-data-section"><div class="sectioning-toolbar"><div><h3>Attendance records</h3></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Session</th><th>Section</th><th>Status</th><th>Check-in</th></tr></thead><tbody>@forelse($user->attendanceRecords as $record)<tr><td>{{ $record->attendanceSession?->title ?? '—' }}</td><td>{{ $record->attendanceSession?->section?->code ?? '—' }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->checked_in_at?->format('M d, Y h:i A') ?? '—' }}</td></tr>@empty<tr><td colspan="4" class="muted-cell">No attendance records in this component.</td></tr>@endforelse</tbody></table></div></section>

<section class="card user-table-card student-data-section"><div class="sectioning-toolbar"><div><h3>Assessment records</h3></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Assessment</th><th>Section</th><th>Submitted</th><th>Score</th><th>Feedback</th></tr></thead><tbody>@forelse($user->assessmentSubmissions as $submission)<tr><td>{{ $submission->assessment?->title ?? '—' }}</td><td>{{ $submission->assessment?->section?->code ?? '—' }}</td><td>{{ $submission->submitted_at?->format('M d, Y h:i A') ?? '—' }}</td><td>{{ $submission->score === null ? 'Pending' : number_format($submission->score,2).' / '.number_format($submission->assessment?->max_score ?? 0,2) }}</td><td>{{ $submission->feedback ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="muted-cell">No assessment records in this component.</td></tr>@endforelse</tbody></table></div></section>
@endif
@endsection
