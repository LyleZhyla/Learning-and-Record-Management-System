@extends('layouts.coordinator')

@section('title', 'Coordinator Dashboard')
@section('page-title', 'Coordinator Dashboard')

@section('content')
<section class="welcome-banner coordinator-dashboard-hero">
    <div>
        <span class="eyebrow">{{ $assignedComponent?->code ?? 'No component assigned' }} command center</span>
        <h2>Good day, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
        <p>@if($assignedComponent) See where {{ $assignedComponent->name }} needs attention—from attendance and section load to pending grading. @else Ask the Super Admin to assign an NSTP component to this coordinator account. @endif</p>
    </div>
    <img class="snapie-character snapie-banner-character" src="{{ asset('images/characters/snapie-wave.webp') }}" alt="SNAPIE mascot waving">
    <span class="workspace-date">{{ now()->format('l') }}<strong>{{ now()->format('M d, Y') }}</strong></span>
</section>

<section class="metric-grid" aria-label="Coordinator operational summary">
    <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>ACTIVE STUDENTS</small><strong>{{ number_format($studentCount) }}</strong><p>Unique component students</p></div></article>
    <article class="metric-card"><span class="metric-icon green">◎</span><div><small>FACILITATORS</small><strong>{{ number_format($facilitatorCount) }}</strong><p>Assigned to the component</p></div></article>
    <article class="metric-card"><span class="metric-icon orange">▦</span><div><small>ACTIVE SECTIONS</small><strong>{{ number_format($sectionCount) }}</strong><p>Within assigned component</p></div></article>
    <article class="metric-card"><span class="metric-icon violet">✓</span><div><small>ATTENDANCE RATE</small><strong>{{ number_format($attendanceRate, 1) }}%</strong><p>{{ number_format($attendanceTotal) }} recorded attendances</p></div></article>
</section>

<section class="coordinator-action-strip" aria-labelledby="coordinator-action-title">
    <div class="coordinator-action-heading"><span class="eyebrow">Needs attention</span><h3 id="coordinator-action-title">Operational checklist</h3></div>
    <a href="{{ route('coordinator.accounts.index') }}" class="coordinator-action-item {{ $unsectionedStudentCount > 0 ? 'warning' : 'clear' }}"><span>01</span><div><strong>{{ number_format($unsectionedStudentCount) }}</strong><small>students without section</small></div></a>
    <a href="{{ route('coordinator.sections.index') }}" class="coordinator-action-item {{ $sectionsWithoutFacilitator > 0 ? 'warning' : 'clear' }}"><span>02</span><div><strong>{{ number_format($sectionsWithoutFacilitator) }}</strong><small>sections without facilitator</small></div></a>
    <a href="{{ route('coordinator.performance.index') }}" class="coordinator-action-item {{ $pendingGradingCount > 0 ? 'warning' : 'clear' }}"><span>03</span><div><strong>{{ number_format($pendingGradingCount) }}</strong><small>submissions awaiting grade</small></div></a>
    <a href="{{ route('coordinator.attendance.index') }}" class="coordinator-action-item {{ $openSessionCount > 0 ? 'active' : 'clear' }}"><span>04</span><div><strong>{{ number_format($openSessionCount) }}</strong><small>open attendance sessions</small></div></a>
</section>

<section class="coordinator-analytics-grid" aria-label="Coordinator graphical analytics">
    <article class="card coordinator-attendance-card">
        <div class="report-chart-heading">
            <div><span class="eyebrow">Attendance health</span><h3>Attendance status</h3><p>Distribution of all recorded attendance in {{ $assignedComponent?->code ?? 'the assigned component' }}.</p></div>
            <a class="text-link" href="{{ route('coordinator.attendance.index') }}">View attendance →</a>
        </div>
        <div class="coordinator-donut-layout">
            <div class="coordinator-donut {{ $attendanceTotal === 0 ? 'empty' : '' }}" style="--present-end: {{ $attendanceBreakdown['present_percentage'] }}%; --late-end: {{ $attendanceBreakdown['present_percentage'] + $attendanceBreakdown['late_percentage'] }}%;" role="img" aria-label="Attendance rate: {{ number_format($attendanceRate, 1) }} percent">
                <div><strong>{{ number_format($attendanceRate, 1) }}%</strong><small>attendance rate</small></div>
            </div>
            <div class="coordinator-chart-legend">
                <div><i class="present"></i><span>Present<small>On-time check-ins</small></span><strong>{{ number_format($attendanceBreakdown['present']) }}</strong></div>
                <div><i class="late"></i><span>Late<small>Late check-ins</small></span><strong>{{ number_format($attendanceBreakdown['late']) }}</strong></div>
                <div><i class="absent"></i><span>Absent<small>Missed sessions</small></span><strong>{{ number_format($attendanceBreakdown['absent']) }}</strong></div>
            </div>
        </div>
    </article>

    <article class="card coordinator-section-chart">
        <div class="report-chart-heading">
            <div><span class="eyebrow">Section capacity</span><h3>Students per section</h3><p>Enrollment load compared with each active section's capacity.</p></div>
            <a class="text-link" href="{{ route('coordinator.sections.index') }}">View sections →</a>
        </div>
        <div class="coordinator-section-bars" role="list" aria-label="Student enrollment by section">
            @forelse($sections as $section)
                @php($sectionUsage = $section->capacity > 0 ? min(100, ($section->enrollments_count / $section->capacity) * 100) : (($section->enrollments_count / $largestSectionEnrollment) * 100))
                <div class="coordinator-section-row" role="listitem">
                    <div class="coordinator-section-label"><strong>{{ $section->name }}</strong><small>{{ $section->facilitator?->name ?? 'No facilitator assigned' }}</small></div>
                    <div class="coordinator-section-track" role="progressbar" aria-label="{{ $section->name }} enrollment" aria-valuemin="0" aria-valuemax="{{ max(1, $section->capacity) }}" aria-valuenow="{{ $section->enrollments_count }}"><span class="{{ $sectionUsage >= 90 ? 'near-capacity' : '' }}" style="width: {{ $sectionUsage }}%"></span></div>
                    <strong class="coordinator-section-value">{{ $section->enrollments_count }}<small>/ {{ $section->capacity }}</small></strong>
                </div>
            @empty
                <div class="empty-state"><strong>No active sections yet</strong><span>Active sections will appear in this distribution chart.</span></div>
            @endforelse
        </div>
    </article>

    <article class="card coordinator-grading-card">
        <div class="report-chart-heading">
            <div><span class="eyebrow">Assessment workflow</span><h3>Grading completion</h3><p>Submitted assessment work that has already received a score.</p></div>
        </div>
        <div class="coordinator-grading-visual">
            <div class="coordinator-progress-ring" style="--progress: {{ $gradingRate * 3.6 }}deg" role="progressbar" aria-label="Grading completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $gradingRate }}"><strong>{{ number_format($gradingRate, 1) }}%</strong></div>
            <div><strong>{{ number_format($gradedCount) }} graded</strong><span>of {{ number_format($submissionTotal) }} submitted</span><small>{{ number_format($pendingGradingCount) }} still awaiting a score</small></div>
        </div>
        <a class="secondary-outline-button" href="{{ route('coordinator.performance.index') }}">Review performance and grades</a>
    </article>

    <article class="card coordinator-recent-card">
        <div class="card-heading"><div><span class="eyebrow">Recent activity</span><h3>Latest attendance sessions</h3><p>Newest QR attendance activity for the assigned component.</p></div><a class="text-link" href="{{ route('coordinator.attendance.index') }}">View all →</a></div>
        <div class="module-list">
            @forelse($recentSessions as $session)
                <div class="module-item"><span class="step done">{{ $session->section->component->code[0] }}</span><div><strong>{{ $session->title }}</strong><p>{{ $session->section->name }} · {{ $session->starts_at->format('M d, Y h:i A') }} · {{ $session->records_count }} records</p></div><span class="state {{ $session->status === 'open' ? 'done' : '' }}">{{ $session->status }}</span></div>
            @empty
                <div class="empty-state"><strong>No attendance sessions yet</strong><span>Facilitator sessions will appear here.</span></div>
            @endforelse
        </div>
    </article>
</section>

@if($assignedComponent)
<a class="component-card coordinator-component-card {{ strtolower($assignedComponent->code) }}" href="{{ route('coordinator.components.index') }}">
    <x-component-logo :component-code="$assignedComponent->code" :logo-path="$assignedComponent->logo_path" class="component-symbol" />
    <div><span>Assigned NSTP component</span><h3>{{ $assignedComponent->code }}</h3><p>{{ $assignedComponent->name }} · {{ $assignedComponent->sections_count }} total sections · {{ $assignedComponent->enrollments_count }} enrollment records</p></div>
    <span class="component-state">Open component overview →</span>
</a>
@endif

<section class="card admin-guide-preview" aria-labelledby="coordinator-guide-preview-title">
    <div class="admin-guide-preview-icon" aria-hidden="true">?</div>
    <div><span class="eyebrow">Coordinator guide</span><h3 id="coordinator-guide-preview-title">Need help with the next step?</h3><p>Follow the recommended oversight workflow and open every Coordinator tool from one guided overview.</p></div>
    <div class="admin-guide-preview-actions"><button class="primary-button" type="button" data-start-coordinator-tour>Start guided tour <span aria-hidden="true">→</span></button><a href="{{ route('coordinator.community-projects.index') }}">Community projects →</a><a href="{{ route('coordinator.system-guide') }}">Read the full guide</a></div>
</section>
@endsection
