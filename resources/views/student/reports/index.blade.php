@extends('layouts.student')

@section('title', 'Reports')
@section('page-title', 'Reports')

@section('content')
    <section class="welcome-banner report-welcome">
        <div>
            <span class="eyebrow">Personal reporting center</span>
            <h2>My NSTP report</h2>
            <p>Review your current enrollment, attendance, submissions, and grade standing in one private page. Only records belonging to your account are shown.</p>
        </div>
        <span class="workspace-date">Generated<strong>{{ now()->format('M d, Y · h:i A') }}</strong></span>
    </section>

    <section class="metric-grid" aria-label="Personal report overview">
        <article class="metric-card"><span class="metric-icon blue">▣</span><div><small>ATTENDANCE RECORDS</small><strong>{{ $metrics['attendance_records'] }}</strong><p>Your recorded NSTP sessions</p></div></article>
        <article class="metric-card"><span class="metric-icon green">✓</span><div><small>ATTENDANCE RATE</small><strong>{{ number_format($metrics['attendance_rate'], 1) }}%</strong><p>Present and late records</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">▤</span><div><small>SUBMISSIONS</small><strong>{{ $metrics['submissions'] }}</strong><p>Assessment work submitted</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">◎</span><div><small>CURRENT GRADE</small><strong>{{ $gradeSummary && $gradeSummary['grade'] !== null ? number_format($gradeSummary['grade'], 2) : '—' }}</strong><p>{{ $metrics['graded_submissions'] }} graded submission{{ $metrics['graded_submissions'] === 1 ? '' : 's' }}</p></div></article>
    </section>

    <section class="card report-personal-summary">
        <div class="card-heading"><div><span class="eyebrow">Current enrollment</span><h3>Academic assignment</h3><p>Your latest active NSTP enrollment and assigned teaching coverage.</p></div><span class="status-badge {{ $enrollment ? 'active' : 'inactive' }}"><i></i>{{ $enrollment ? ucfirst($enrollment->status) : 'Not enrolled' }}</span></div>
        <div class="visible-data-grid">
            <div><span>Component</span><strong>{{ $enrollment?->component?->code ?? 'Unassigned' }}</strong></div>
            <div><span>Section</span><strong>{{ $enrollment?->section?->code ?? 'Unassigned' }}</strong></div>
            <div><span>Academic term</span><strong>{{ $enrollment ? (($enrollment->section?->semesterLabel() ?? str($enrollment->semester)->headline()).' '.$enrollment->academic_year) : 'Not available' }}</strong></div>
            <div><span>Facilitator</span><strong>{{ $enrollment?->section?->facilitator?->name ?? 'Unassigned' }}</strong></div>
        </div>
    </section>

    <section class="card user-table-card report-result-card">
        <div class="report-result-heading"><div><span class="eyebrow">Attendance</span><h3>Recent attendance records</h3><p>Your latest recorded NSTP attendance.</p></div><a class="secondary-outline-button" href="{{ route('student.attendance.index') }}">View all attendance</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Session</th><th>Component</th><th>Section</th><th>Date</th><th>Status</th><th>Check-in</th></tr></thead><tbody>
            @forelse($attendanceRecords->take(10) as $record)
                <tr><td><strong>{{ $record->attendanceSession->title }}</strong></td><td>{{ $record->attendanceSession->section->component->code }}</td><td>{{ $record->attendanceSession->section->code }}</td><td>{{ $record->attendanceSession->starts_at->format('M d, Y') }}</td><td><span class="status-badge {{ in_array($record->status, ['present', 'late']) ? 'active' : 'inactive' }}"><i></i>{{ ucfirst($record->status) }}</span></td><td>{{ $record->checked_in_at?->format('h:i A') ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>No attendance records yet</strong><span>Your recorded sessions will appear here.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    <section class="card user-table-card report-result-card">
        <div class="report-result-heading"><div><span class="eyebrow">Assessment activity</span><h3>Recent submissions</h3><p>Your submitted and graded assessment work.</p></div><a class="secondary-outline-button" href="{{ route('student.grades.index') }}">View grade breakdown</a></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Assessment</th><th>Component</th><th>Submitted</th><th>Score</th><th>Feedback</th></tr></thead><tbody>
            @forelse($submissions->take(10) as $submission)
                <tr><td><strong>{{ $submission->assessment->title }}</strong></td><td>{{ $submission->assessment->section->component->code }}</td><td>{{ $submission->submitted_at?->format('M d, Y · h:i A') ?? '—' }}</td><td>{{ $submission->score === null ? 'Pending' : number_format($submission->score, 2).' / '.number_format($submission->assessment->max_score, 2) }}</td><td>{{ $submission->feedback ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="5"><div class="empty-state"><strong>No submissions yet</strong><span>Your assessment submissions will appear here.</span></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endsection
