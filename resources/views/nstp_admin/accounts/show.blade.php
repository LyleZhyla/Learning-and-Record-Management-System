@extends('layouts.nstp-admin')
@section('title', 'Account Details') @section('page-title', 'Account Details')
@section('content')
<div class="back-row"><a href="{{ route('nstp_admin.accounts.index') }}">← Back to account directory</a></div>

@if(!$user->isStudent())
<section class="card restricted-account-profile">
    <div class="account-heading"><span class="large-avatar small">{{ strtoupper(substr($user->name,0,1)) }}</span><div><span class="eyebrow">{{ $user->roleLabel() }}</span><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div></div>
    <div class="visible-data-grid"><div><small>Name</small><strong>{{ $user->name }}</strong></div><div><small>Email</small><strong>{{ $user->email }}</strong></div><div class="full"><small>Component handled</small><p>@forelse($components as $component)<span class="component-mini-badge">{{ $component->code }} · {{ $component->name }}</span>@empty<span class="muted-cell">No component assignment</span>@endforelse @if($user->isCoordinator())<span class="muted-cell">System-wide coordinator coverage under the current setup.</span>@endif</p></div></div>
</section>
<section class="card password-boundary-note"><span>🔒</span><div><strong>Limited account details</strong><p>Only the name, email, and component coverage are available to NSTP Admin. Contact the Super Admin for password or account recovery concerns.</p></div></section>
@else
<section class="card student-record-header">
    <div><span class="eyebrow">Student account</span><h2>{{ $user->name }}</h2><p>{{ $user->email }}</p></div>
    <div><small>Status</small><strong>{{ $user->statusLabel() }}</strong></div><div><small>Last sign in</small><strong>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Never' }}</strong></div><div><small>Registered</small><strong>{{ $user->created_at?->format('M d, Y') }}</strong></div><div><small>Attendance QR</small><strong>{{ $user->student_qr_token ? 'Issued' : 'Not issued' }}</strong></div>
</section>

<section class="card user-table-card student-data-section"><div class="sectioning-toolbar"><div><span class="eyebrow">Current available student data</span><h3>Enrollment history</h3></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Component</th><th>Section</th><th>Academic year</th><th>Semester</th><th>Status</th></tr></thead><tbody>@forelse($user->nstpEnrollments as $enrollment)<tr><td>{{ $enrollment->component?->code ?? '—' }}</td><td>{{ $enrollment->section?->code ?? 'Unassigned' }}</td><td>{{ $enrollment->academic_year }}</td><td>{{ $enrollment->section?->semesterLabel() ?? ucfirst($enrollment->semester) }}</td><td>{{ ucfirst($enrollment->status) }}</td></tr>@empty<tr><td colspan="5" class="muted-cell">No enrollment records.</td></tr>@endforelse</tbody></table></div></section>

<section class="card user-table-card student-data-section"><div class="sectioning-toolbar"><div><h3>Attendance records</h3><p class="muted-cell">{{ $user->attendanceRecords->count() }} recorded sessions</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Session</th><th>Component / section</th><th>Status</th><th>Check-in</th><th>Source</th></tr></thead><tbody>@forelse($user->attendanceRecords as $record)<tr><td>{{ $record->attendanceSession?->title ?? '—' }}</td><td>{{ $record->attendanceSession?->section?->component?->code ?? '—' }} · {{ $record->attendanceSession?->section?->code ?? '—' }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->checked_in_at?->format('M d, Y h:i A') ?? '—' }}</td><td>{{ strtoupper($record->source) }}</td></tr>@empty<tr><td colspan="5" class="muted-cell">No attendance records.</td></tr>@endforelse</tbody></table></div></section>

<section class="card user-table-card student-data-section"><div class="sectioning-toolbar"><div><h3>Assessment records</h3><p class="muted-cell">Submitted work, recorded scores, and feedback currently stored in SNAPIE.</p></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Assessment</th><th>Component / section</th><th>Submitted</th><th>Score</th><th>Feedback</th></tr></thead><tbody>@forelse($user->assessmentSubmissions as $submission)<tr><td>{{ $submission->assessment?->title ?? '—' }}</td><td>{{ $submission->assessment?->section?->component?->code ?? '—' }} · {{ $submission->assessment?->section?->code ?? '—' }}</td><td>{{ $submission->submitted_at?->format('M d, Y h:i A') ?? '—' }}</td><td>{{ $submission->score === null ? 'Pending' : number_format($submission->score,2).' / '.number_format($submission->assessment?->max_score ?? 0,2) }}</td><td>{{ $submission->feedback ?: '—' }}</td></tr>@empty<tr><td colspan="5" class="muted-cell">No assessment records.</td></tr>@endforelse</tbody></table></div></section>
@endif
@endsection
