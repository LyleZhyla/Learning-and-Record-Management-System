@extends('layouts.student')

@section('title', 'Student Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<link rel="stylesheet" href="{{ asset('css/student-dashboard.css') }}?v={{ filemtime(public_path('css/student-dashboard.css')) }}">

<section class="welcome-banner student-dashboard-welcome">
    <div class="student-welcome-copy">
        <span class="eyebrow">Student learning portal</span>
        <h2>Good day, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
        <p>
            @if($enrollment?->section)
                You are enrolled in {{ $enrollment->component->code }}, section {{ $enrollment->section->name }} for {{ $enrollment->section->semesterLabel() }} {{ $enrollment->academic_year }}.
            @elseif($enrollment)
                Your {{ $enrollment->component->code }} enrollment is approved and awaiting section assignment.
            @elseif($pendingEnrollment)
                Your {{ $pendingEnrollment->rotc_category }} request is pending ROTC coordinator approval.
            @else
                Your NSTP enrollment and section have not been assigned yet.
            @endif
        </p>
        <div class="student-hero-actions">
            @if($stats['assessments_remaining'] > 0)
                <a class="secondary-button" href="{{ route('student.assessments.index') }}">Continue pending work</a>
            @else
                <a class="secondary-button" href="{{ route('student.community-projects.index') }}">Explore community projects</a>
            @endif
            <a class="student-hero-link" href="{{ route('student.system-guide') }}">Open student guide →</a>
        </div>
    </div>
    <img class="snapie-character snapie-banner-character" src="{{ asset('images/characters/snapie-wave.webp') }}" alt="SNAPIE mascot waving">
</section>

<div class="metric-grid student-dashboard-metrics">
    <a class="metric-card" href="{{ route('student.attendance.index') }}"><span class="metric-icon blue">▣</span><div><small>ATTENDED SESSIONS</small><strong>{{ $stats['attendance_completed'] }}/{{ $stats['attendance_total'] }}</strong><p>{{ $stats['attendance_remaining'] }} session{{ $stats['attendance_remaining'] === 1 ? '' : 's' }} not attended</p></div></a>
    <a class="metric-card" href="{{ route('student.materials.index') }}"><span class="metric-icon green">▤</span><div><small>LEARNING MATERIALS</small><strong>{{ $stats['materials'] }}</strong><p>Available resources</p></div></a>
    <a class="metric-card" href="{{ route('student.assessments.index') }}"><span class="metric-icon orange">✓</span><div><small>ASSESSMENTS SUBMITTED</small><strong>{{ $stats['assessments_completed'] }}/{{ $stats['assessments_total'] }}</strong><p>{{ $stats['assessments_remaining'] }} assessment{{ $stats['assessments_remaining'] === 1 ? '' : 's' }} remaining</p></div></a>
    <a class="metric-card" href="{{ route('student.grades.index') }}"><span class="metric-icon violet">◎</span><div><small>COMPUTED GRADE</small><strong>{{ $stats['grade'] === null ? '—' : number_format($stats['grade'], 2) }}</strong><p>Based on graded work</p></div></a>
</div>

@if($enrollment?->section)
    <section class="student-progress-grid" aria-label="Student progress overview">
        <article class="card student-progress-card">
            <div class="card-heading"><div><span class="eyebrow">Attendance progress</span><h3>Session participation</h3><p>Your present and late records count toward attended sessions.</p></div><a class="text-link" href="{{ route('student.attendance.index') }}">View history →</a></div>
            <div class="student-ring-layout">
                <div class="student-progress-ring" style="--progress: {{ $attendanceRate * 3.6 }}deg" role="progressbar" aria-label="Attendance completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $attendanceRate }}"><strong>{{ number_format($attendanceRate, 1) }}%</strong><small>attended</small></div>
                <div class="student-status-list">
                    <div><i class="present"></i><span>Present</span><strong>{{ $attendanceBreakdown['present'] }}</strong></div>
                    <div><i class="late"></i><span>Late</span><strong>{{ $attendanceBreakdown['late'] }}</strong></div>
                    <div><i class="absent"></i><span>Absent</span><strong>{{ $attendanceBreakdown['absent'] }}</strong></div>
                    <div><i class="unrecorded"></i><span>Not recorded</span><strong>{{ max(0, $stats['attendance_total'] - array_sum($attendanceBreakdown)) }}</strong></div>
                </div>
            </div>
        </article>

        <article class="card student-progress-card">
            <div class="card-heading"><div><span class="eyebrow">Assessment progress</span><h3>Coursework completion</h3><p>Published activities submitted for your current section.</p></div><a class="text-link" href="{{ route('student.assessments.index') }}">Open classwork →</a></div>
            <div class="student-assessment-summary">
                <div class="student-assessment-value"><strong>{{ number_format($assessmentRate, 1) }}%</strong><span>complete</span></div>
                <div class="student-progress-track" role="progressbar" aria-label="Assessment completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $assessmentRate }}"><span style="width: {{ $assessmentRate }}%"></span></div>
                <div class="student-assessment-counts"><span><strong>{{ $stats['assessments_completed'] }}</strong> submitted</span><span><strong>{{ $stats['assessments_remaining'] }}</strong> remaining</span></div>
            </div>
        </article>
    </section>

    <section class="student-dashboard-detail-grid">
        <article class="card student-work-card">
            <div class="card-heading"><div><span class="eyebrow">Next actions</span><h3>Pending assessments</h3><p>Prioritized by due date so you can focus on what comes next.</p></div><a class="text-link" href="{{ route('student.assessments.index') }}">View all →</a></div>
            <div class="student-work-list">
                @forelse($upcomingAssessments as $assessment)
                    @php($isOverdue = $assessment->due_at?->isPast())
                    <a href="{{ route('student.assessments.show', $assessment) }}">
                        <span class="student-work-icon">✓</span>
                        <span><strong>{{ $assessment->title }}</strong><small>{{ ucfirst($assessment->type) }} · {{ number_format((float) $assessment->max_score, 0) }} points</small></span>
                        <span class="student-work-deadline {{ $isOverdue ? 'overdue' : '' }}">{{ $assessment->due_at ? ($isOverdue ? 'Overdue · ' : 'Due ').$assessment->due_at->format('M d, g:i A') : 'No due date' }}</span>
                    </a>
                @empty
                    <div class="student-empty-state"><span>✓</span><strong>You are all caught up</strong><small>No published assessments are waiting for submission.</small></div>
                @endforelse
            </div>
        </article>

        <article class="card student-class-card">
            <div class="card-heading"><div><span class="eyebrow">Current class</span><h3>{{ $enrollment->section->name }}</h3><p>{{ $enrollment->component->name }}</p></div><span class="student-component-badge">{{ $enrollment->component->code }}</span></div>
            <dl class="student-class-details">
                <div><dt>Academic term</dt><dd>{{ $enrollment->section->semesterLabel() }} · {{ $enrollment->academic_year }}</dd></div>
                <div><dt>Facilitator</dt><dd>{{ $enrollment->section->facilitator?->name ?? 'To be assigned' }}</dd></div>
                <div><dt>Schedule</dt><dd>@if($enrollment->section->schedule){{ \App\Http\Controllers\Learning\ScheduleController::DAYS[$enrollment->section->schedule->day_of_week] }} · {{ \Carbon\Carbon::parse($enrollment->section->schedule->starts_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($enrollment->section->schedule->ends_at)->format('g:i A') }}@else To be announced @endif</dd></div>
            </dl>
            <a class="secondary-outline-button student-class-action" href="{{ route('student.messages.index') }}">Message your facilitator</a>
        </article>
    </section>

    <section class="card student-recent-card">
        <div class="card-heading"><div><span class="eyebrow">Recent activity</span><h3>Latest attendance records</h3><p>Your most recently recorded NSTP sessions.</p></div><a class="text-link" href="{{ route('student.attendance.index') }}">Full attendance →</a></div>
        <div class="student-recent-list">
            @forelse($recentAttendance as $record)
                <div><span class="student-attendance-state {{ $record->status }}">{{ strtoupper(substr($record->status, 0, 1)) }}</span><span><strong>{{ $record->attendanceSession->title }}</strong><small>{{ $record->attendanceSession->starts_at->format('M d, Y · g:i A') }}</small></span><span class="status-badge {{ $record->status === 'present' ? 'active' : '' }}">{{ ucfirst($record->status) }}</span></div>
            @empty
                <div class="student-empty-state compact"><span>▣</span><strong>No attendance records yet</strong><small>Your first recorded session will appear here.</small></div>
            @endforelse
        </div>
    </section>
@else
    <section class="card student-enrollment-empty">
        <div class="student-enrollment-icon" aria-hidden="true">◈</div>
        <div><span class="eyebrow">Enrollment status</span><h3>{{ $pendingEnrollment ? 'Approval in progress' : ($enrollment ? 'Section assignment pending' : 'Complete your NSTP setup') }}</h3><p>@if($pendingEnrollment)Your request is already with the ROTC coordinator. You can continue using your account while waiting for the decision.@elseif($enrollment)Your enrollment is approved. Dashboard progress will appear as soon as a section is assigned.@else Select your NSTP component to begin enrollment and unlock your class dashboard.@endif</p></div>
        @if(!$pendingEnrollment && !$enrollment)<a class="primary-button compact" href="{{ route('student.component.edit') }}">Choose NSTP component</a>@endif
    </section>
@endif

<section class="card admin-guide-preview student-guide-preview">
    <span class="admin-guide-preview-icon" aria-hidden="true">?</span>
    <div><span class="eyebrow">Student portal tutorial</span><h3>New to Smart NSTP?</h3><p>Take an interactive tour of enrollment, attendance, learning, assessments, grades, communication, AI assistance, and account security.</p></div>
    <div class="admin-guide-preview-actions"><button class="primary-button" type="button" data-start-student-tour>Start guided tour</button><a href="{{ route('student.system-guide') }}">Read the full system guide →</a></div>
</section>
@endsection
