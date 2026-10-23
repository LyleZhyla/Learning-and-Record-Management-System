@extends('layouts.nstp-admin')

@section('title', 'NSTP Admin Dashboard')
@section('page-title', 'NSTP Admin Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/activity-calendar.css') }}?v={{ filemtime(public_path('css/activity-calendar.css')) }}">
@endpush

@section('content')
    <section class="welcome-banner nstp-welcome">
        <div>
            <span class="eyebrow">Student participation overview · {{ $academicTerm }}</span>
            <h2>Good day, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
            <p>Track student enrollment, section assignment, and attendance across CWTS, LTS, and ROTC from one workspace.</p>
        </div>
        <img class="snapie-character snapie-banner-character" src="{{ asset('images/characters/snapie-wave.webp') }}" alt="SNAPIE mascot waving">
        <span class="workspace-date">{{ now()->format('l') }}<strong>{{ now()->format('M d, Y') }}</strong></span>
    </section>

    <section class="metric-grid" aria-label="Current-term student overview">
        <article class="metric-card"><span class="metric-icon blue">♟</span><div><small>Enrolled this term</small><strong>{{ number_format($enrolledStudentCount) }}</strong><p>of {{ number_format($studentCount) }} active students</p></div></article>
        <article class="metric-card"><span class="metric-icon green">▦</span><div><small>Assigned to sections</small><strong>{{ number_format($sectionedStudentCount) }}</strong><p>{{ number_format($sectioningRate, 1) }}% sectioning coverage</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">✓</span><div><small>Attendance rate</small><strong>{{ number_format($attendanceRate, 1) }}%</strong><p>{{ number_format($attendanceRecordCount) }} attendance records</p></div></article>
        <article class="metric-card" data-unassigned-student-count="{{ $unassignedStudentCount }}"><span class="metric-icon violet">!</span><div><small>Without component</small><strong>{{ $unassignedStudentCount }}</strong><p>Active students this term</p></div></article>
    </section>

    <section class="card admin-guide-preview" aria-labelledby="nstp-admin-guide-preview-title">
        <div class="admin-guide-preview-icon" aria-hidden="true">?</div>
        <div><span class="eyebrow">NSTP Administrator guide</span><h3 id="nstp-admin-guide-preview-title">Not sure where to begin?</h3><p>Follow the recommended operating order and open each NSTP Administration tool from one guided overview.</p></div>
        <div class="admin-guide-preview-actions"><button class="primary-button" type="button" data-start-nstp-admin-tour>Start guided tour <span aria-hidden="true">→</span></button><a href="{{ route('nstp_admin.community-projects.index') }}">Community projects →</a><a href="{{ route('nstp_admin.project-tasks.index') }}">Task monitoring →</a><a href="{{ route('nstp_admin.evaluations.index') }}">Evaluations →</a><a href="{{ route('nstp_admin.facilitator-requirements.index') }}">Facilitator compliance →</a><a href="{{ route('nstp_admin.honoraria.index') }}">Honorarium documents →</a><a href="{{ route('nstp_admin.engagement-analytics.index') }}">Engagement analytics →</a><a href="{{ route('nstp_admin.system-guide') }}">Read the full guide</a></div>
    </section>

    <section class="student-dashboard-analytics" aria-labelledby="student-analytics-title">
        <div class="report-analytics-heading">
            <div><span class="eyebrow">Current-term analytics</span><h3 id="student-analytics-title">Student participation by component</h3><p>Enrollment, section assignment, and attendance for {{ $academicTerm }}.</p></div>
            <a class="secondary-outline-button" href="{{ route('nstp_admin.reports.index') }}">Open reports center →</a>
        </div>

        <div class="student-analytics-grid">
            <article class="card student-enrollment-chart">
                <div class="report-chart-heading">
                    <div><span class="eyebrow">Enrollment distribution</span><h4>Students per component</h4><p>Current enrollments grouped under CWTS, LTS, and ROTC.</p></div>
                    <span class="report-chart-total"><strong>{{ number_format($enrolledStudentCount) }}</strong><small>Total enrolled</small></span>
                </div>
                <div class="dashboard-component-bars" style="--component-count: {{ max(1, $componentAnalytics->count()) }}" role="list" aria-label="Current enrollment by NSTP component">
                    @foreach($componentAnalytics as $component)
                        <div class="dashboard-component-column" role="listitem" aria-label="{{ $component['code'] }}: {{ $component['enrolled'] }} enrolled students">
                            <div class="dashboard-component-track"><span class="component-{{ strtolower($component['code']) }}" style="height: {{ ($component['enrolled'] / $largestEnrollment) * 100 }}%"><b>{{ number_format($component['enrolled']) }}</b></span></div>
                            <div><strong>{{ $component['code'] }}</strong><small>{{ $component['sections'] }} active {{ str('section')->plural($component['sections']) }}</small></div>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="card student-attendance-chart">
                <div class="report-chart-heading">
                    <div><span class="eyebrow">Attendance per component</span><h4>Present, late, and absent</h4><p>Attendance record status and participation rate by program.</p></div>
                    <span class="report-chart-total green"><strong>{{ number_format($attendanceRate, 1) }}%</strong><small>Overall rate</small></span>
                </div>
                <div class="attendance-status-legend" aria-label="Attendance status legend"><span><i class="present"></i>Present</span><span><i class="late"></i>Late</span><span><i class="absent"></i>Absent</span></div>
                <div class="component-attendance-list">
                    @foreach($componentAnalytics as $component)
                        <div class="component-attendance-row">
                            <div class="component-attendance-name"><strong>{{ $component['code'] }}</strong><small>{{ number_format($component['attendance_records']) }} records</small></div>
                            @if($component['attendance_records'] > 0)
                                <div class="component-attendance-track" aria-label="{{ $component['code'] }}: {{ $component['present'] }} present, {{ $component['late'] }} late, {{ $component['absent'] }} absent">
                                    @if($component['present'] > 0)<span class="present" style="width: {{ $component['present_percentage'] }}%"></span>@endif
                                    @if($component['late'] > 0)<span class="late" style="width: {{ $component['late_percentage'] }}%"></span>@endif
                                    @if($component['absent'] > 0)<span class="absent" style="width: {{ $component['absent_percentage'] }}%"></span>@endif
                                </div>
                                <strong class="component-attendance-rate">{{ number_format($component['attendance_rate'], 1) }}%</strong>
                            @else
                                <div class="component-attendance-track empty"><span></span></div><strong class="component-attendance-rate muted">—</strong>
                            @endif
                        </div>
                    @endforeach
                </div>
            </article>
        </div>

        <article class="card sectioning-coverage-card">
            <div class="sectioning-coverage-copy"><span class="eyebrow">Section readiness</span><h4>{{ number_format($sectioningRate, 1) }}% of enrolled students have a section</h4><p>{{ number_format($sectionedStudentCount) }} assigned · {{ number_format($unsectionedStudentCount) }} still need section placement.</p></div>
            <div class="sectioning-coverage-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $sectioningRate }}"><span style="width: {{ $sectioningRate }}%"></span></div>
            @if($unsectionedStudentCount > 0)<a class="table-action" href="{{ route('nstp_admin.sectioning.index') }}">Assign students →</a>@else<span class="status-badge active"><i></i>All sectioned</span>@endif
        </article>
    </section>

    <x-activity-calendar
        :calendar="$activityCalendar"
        dashboard-route="nstp_admin.dashboard"
        description="Assessment deadlines, project activities, and attendance sessions across all active NSTP sections this term."
    />

    <section class="component-overview" aria-label="NSTP components">
        <a class="component-card cwts" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'CWTS')?->id]) }}"><x-component-logo component-code="CWTS" :logo-path="$components->firstWhere('code', 'CWTS')?->logo_path" class="component-symbol" /><div><span>{{ $components->firstWhere('code', 'CWTS')?->name ?? 'Civic Welfare Training Service' }}</span><h3>CWTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
        <a class="component-card lts" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'LTS')?->id]) }}"><x-component-logo component-code="LTS" :logo-path="$components->firstWhere('code', 'LTS')?->logo_path" class="component-symbol" /><div><span>{{ $components->firstWhere('code', 'LTS')?->name ?? 'Literacy Training Service' }}</span><h3>LTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
        <a class="component-card rotc" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'ROTC')?->id]) }}"><x-component-logo component-code="ROTC" :logo-path="$components->firstWhere('code', 'ROTC')?->logo_path" class="component-symbol" /><div><span>{{ $components->firstWhere('code', 'ROTC')?->name ?? "Reserve Officers' Training Corps" }}</span><h3>ROTC</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
    </section>

    <section class="card">
        <div class="card-heading"><div><span class="eyebrow">Latest registrations</span><h3>Recent NSTP accounts</h3><p>Most recently added student accounts.</p></div><span class="pill">{{ $recentAccounts->count() }} shown</span></div>
        <div class="compact-user-list">
            @forelse ($recentAccounts as $account)
                <div class="compact-user-row">
                    <span class="table-avatar">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                    <div><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small></div>
                    <span class="role-badge role-{{ $account->role }}">{{ $account->roleLabel() }}</span>
                    <span class="status-badge {{ $account->status }}"><i></i>{{ $account->statusLabel() }}</span>
                </div>
            @empty
                <div class="empty-state"><strong>No student accounts yet</strong><span>Student accounts created by the Super Admin will appear here.</span></div>
            @endforelse
        </div>
    </section>
@endsection
