@extends('layouts.facilitator')

@section('title', 'Facilitator Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<link rel="stylesheet" href="{{ asset('css/facilitator-dashboard.css') }}?v={{ filemtime(public_path('css/facilitator-dashboard.css')) }}">

<section class="welcome-banner facilitator-dashboard-hero">
    <div>
        <span class="eyebrow">Facilitator command center</span>
        <h2>Good day, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
        <p>Track attendance, section load, and pending student work across the sections assigned to you.</p>
    </div>
    <img class="snapie-character snapie-banner-character" src="{{ asset('images/characters/snapie-wave.webp') }}" alt="SNAPIE mascot waving">
    <a class="secondary-button" href="{{ route('facilitator.attendance.create') }}">Start attendance session</a>
</section>

<section class="metric-grid" aria-label="Facilitator teaching summary">
    <article class="metric-card"><span class="metric-icon blue">▦</span><div><small>ASSIGNED SECTIONS</small><strong>{{ number_format($stats['sections']) }}</strong><p>Your current teaching load</p></div></article>
    <article class="metric-card"><span class="metric-icon green">♙</span><div><small>ACTIVE STUDENTS</small><strong>{{ number_format($stats['students']) }}</strong><p>Unique enrolled students</p></div></article>
    <article class="metric-card"><span class="metric-icon orange">▣</span><div><small>ATTENDANCE RATE</small><strong>{{ number_format($attendanceRate, 1) }}%</strong><p>{{ number_format($attendanceTotal) }} attendance records</p></div></article>
    <article class="metric-card"><span class="metric-icon violet">✓</span><div><small>UNGRADED WORK</small><strong>{{ number_format($pendingGradingCount) }}</strong><p>Submitted work needing a score</p></div></article>
</section>

<section class="facilitator-action-strip" aria-labelledby="facilitator-action-title">
    <div><span class="eyebrow">Quick actions</span><h3 id="facilitator-action-title">What needs attention</h3></div>
    <a href="{{ route('facilitator.attendance.index') }}" class="facilitator-action-item {{ $openSessionCount > 0 ? 'active' : '' }}"><span>01</span><strong>{{ number_format($openSessionCount) }}</strong><small>open attendance sessions</small></a>
    <a href="{{ route('facilitator.assessments.index') }}" class="facilitator-action-item {{ $pendingGradingCount > 0 ? 'warning' : '' }}"><span>02</span><strong>{{ number_format($pendingGradingCount) }}</strong><small>submissions awaiting grade</small></a>
    <a href="{{ route('facilitator.students.index') }}" class="facilitator-action-item"><span>03</span><strong>{{ number_format($stats['students']) }}</strong><small>students in your roster</small></a>
</section>

<section class="facilitator-dashboard-analytics" aria-labelledby="facilitator-analytics-title">
    <div class="report-analytics-heading">
        <div><span class="eyebrow">Teaching analytics</span><h3 id="facilitator-analytics-title">Section performance at a glance</h3><p>Live records from your assigned sections only.</p></div>
        <a class="text-link" href="{{ route('facilitator.engagement-analytics.index') }}">Open engagement analytics →</a>
    </div>

    <div class="facilitator-analytics-grid">
        <article class="card facilitator-attendance-chart">
            <div class="report-chart-heading">
                <div><span class="eyebrow">Attendance health</span><h4>Status distribution</h4><p>Present, late, and absent records from all your attendance sessions.</p></div>
                <a class="text-link" href="{{ route('facilitator.attendance.index') }}">View records →</a>
            </div>
            <div class="facilitator-donut-layout">
                <div class="facilitator-donut {{ $attendanceTotal === 0 ? 'empty' : '' }}" style="--present-end: {{ $attendanceBreakdown['present_percentage'] }}%; --late-end: {{ $attendanceBreakdown['present_percentage'] + $attendanceBreakdown['late_percentage'] }}%;" role="img" aria-label="Attendance rate: {{ number_format($attendanceRate, 1) }} percent">
                    <div><strong>{{ number_format($attendanceRate, 1) }}%</strong><small>attendance rate</small></div>
                </div>
                <div class="facilitator-chart-legend">
                    <div><i class="present"></i><span>Present<small>On-time attendance</small></span><strong>{{ number_format($attendanceBreakdown['present']) }}</strong></div>
                    <div><i class="late"></i><span>Late<small>Late check-ins</small></span><strong>{{ number_format($attendanceBreakdown['late']) }}</strong></div>
                    <div><i class="absent"></i><span>Absent<small>Missed sessions</small></span><strong>{{ number_format($attendanceBreakdown['absent']) }}</strong></div>
                </div>
            </div>
        </article>

        <article class="card facilitator-section-chart">
            <div class="report-chart-heading">
                <div><span class="eyebrow">Teaching load</span><h4>Students per section</h4><p>Compare the number of actively enrolled students in each assigned section.</p></div>
            </div>
            <div class="facilitator-section-bars" role="list" aria-label="Active students per assigned section">
                @forelse($sections as $section)
                    @php($enrollmentWidth = ($section->enrollments_count / $largestSectionEnrollment) * 100)
                    <div class="facilitator-section-row" role="listitem">
                        <div class="facilitator-section-label"><strong>{{ $section->name }}</strong><small>{{ $section->component->code }} · {{ $section->semesterLabel() }}</small></div>
                        <div class="facilitator-section-track" role="progressbar" aria-label="{{ $section->name }} active enrollment" aria-valuemin="0" aria-valuemax="{{ $largestSectionEnrollment }}" aria-valuenow="{{ $section->enrollments_count }}"><span style="width: {{ $enrollmentWidth }}%"></span></div>
                        <strong class="facilitator-section-value">{{ $section->enrollments_count }}</strong>
                    </div>
                @empty
                    <div class="empty-state"><strong>No section assignment yet</strong><span>Section enrollment analytics will appear once a section is assigned.</span></div>
                @endforelse
            </div>
        </article>

        <article class="card facilitator-grading-chart">
            <div class="report-chart-heading">
                <div><span class="eyebrow">Assessment workflow</span><h4>Grading completion</h4><p>Submitted student work that already has an official score.</p></div>
            </div>
            <div class="facilitator-grading-layout">
                <div class="facilitator-progress-ring" style="--progress: {{ $gradingRate * 3.6 }}deg" role="progressbar" aria-label="Grading completion" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $gradingRate }}"><strong>{{ number_format($gradingRate, 1) }}%</strong></div>
                <div><strong>{{ number_format($gradedCount) }} graded</strong><span>of {{ number_format($submissionTotal) }} submitted</span><small>{{ number_format($pendingGradingCount) }} still awaiting a score</small></div>
            </div>
            <a class="secondary-outline-button" href="{{ route('facilitator.assessments.index') }}">Review assessments</a>
        </article>

        <article class="card facilitator-recent-sessions">
            <div class="card-heading"><div><span class="eyebrow">Recent activity</span><h3>Latest attendance sessions</h3><p>Your newest attendance activity and recorded check-ins.</p></div><a class="text-link" href="{{ route('facilitator.attendance.index') }}">View all →</a></div>
            <div class="module-list">
                @forelse($recentSessions as $session)
                    <div class="module-item"><span class="step done">{{ strtoupper(substr($session->section->component->code, 0, 1)) }}</span><div><strong>{{ $session->title }}</strong><p>{{ $session->section->name }} · {{ $session->starts_at->format('M d, Y g:i A') }} · {{ $session->records_count }} records</p></div><span class="state {{ $session->status === 'open' ? 'done' : '' }}">{{ $session->status }}</span></div>
                @empty
                    <div class="empty-state"><strong>No attendance sessions yet</strong><span>Your latest sessions will appear here.</span></div>
                @endforelse
            </div>
        </article>
    </div>
</section>

<section class="card facilitator-activity-calendar" aria-labelledby="activity-calendar-title">
    <div class="facilitator-calendar-heading">
        <div>
            <span class="eyebrow">Activity calendar</span>
            <h3 id="activity-calendar-title">{{ $calendarMonth->format('F Y') }}</h3>
            <p>Assessment deadlines, project activities, and attendance sessions for your assigned sections.</p>
        </div>
        <div class="facilitator-calendar-navigation" aria-label="Calendar navigation">
            <a href="{{ route('facilitator.dashboard', ['month' => $calendarMonth->subMonth()->format('Y-m')]) }}#activity-calendar-title" aria-label="Previous month">‹</a>
            <a class="today" href="{{ route('facilitator.dashboard') }}#activity-calendar-title">Today</a>
            <a href="{{ route('facilitator.dashboard', ['month' => $calendarMonth->addMonth()->format('Y-m')]) }}#activity-calendar-title" aria-label="Next month">›</a>
        </div>
    </div>

    <div class="facilitator-calendar-layout">
        <div class="facilitator-calendar-shell">
            <div class="facilitator-calendar-weekdays" aria-hidden="true">
                @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)<span>{{ $weekday }}</span>@endforeach
            </div>
            <div class="facilitator-calendar-grid" role="grid" aria-label="Activities for {{ $calendarMonth->format('F Y') }}">
                @foreach($calendarDays as $day)
                    @php($dayEvents = $calendarEventsByDate->get($day->toDateString(), collect()))
                    <article class="facilitator-calendar-day {{ $day->month !== $calendarMonth->month ? 'outside-month' : '' }} {{ $day->isToday() ? 'is-today' : '' }}" role="gridcell" aria-label="{{ $day->format('F j, Y') }}{{ $dayEvents->isNotEmpty() ? ', '.$dayEvents->count().' scheduled items' : '' }}">
                        <time datetime="{{ $day->toDateString() }}">{{ $day->day }}</time>
                        <div class="facilitator-calendar-events">
                            @foreach($dayEvents->take(3) as $event)
                                <a class="facilitator-calendar-event {{ $event['type'] }}" href="{{ $event['url'] }}" title="{{ $event['category'] }}: {{ $event['title'] }}">
                                    @if($event['time'])<span>{{ $event['time'] }}</span>@endif
                                    <strong>{{ $event['title'] }}</strong>
                                </a>
                            @endforeach
                            @if($dayEvents->count() > 3)<span class="facilitator-calendar-more">+{{ $dayEvents->count() - 3 }} more</span>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>

        <aside class="facilitator-calendar-agenda" aria-label="Monthly activity agenda">
            <div class="facilitator-calendar-agenda-heading">
                <div><span class="eyebrow">Monthly agenda</span><h4>{{ $calendarMonthEvents->count() }} scheduled {{ \Illuminate\Support\Str::plural('item', $calendarMonthEvents->count()) }}</h4></div>
                <div class="facilitator-calendar-legend" aria-label="Activity types"><span class="assessment">Assessment</span><span class="project">Project</span><span class="attendance">Attendance</span></div>
            </div>
            <div class="facilitator-calendar-agenda-list">
                @forelse($calendarMonthEvents as $event)
                    <a href="{{ $event['url'] }}" class="facilitator-agenda-item {{ $event['type'] }}">
                        <time datetime="{{ $event['date'] }}"><strong>{{ \Carbon\Carbon::parse($event['date'])->format('d') }}</strong><span>{{ \Carbon\Carbon::parse($event['date'])->format('M') }}</span></time>
                        <div><span>{{ $event['category'] }}{{ $event['time'] ? ' · '.$event['time'] : '' }}</span><strong>{{ $event['title'] }}</strong><small>{{ $event['meta'] }}</small></div>
                    </a>
                @empty
                    <div class="facilitator-calendar-empty"><span aria-hidden="true">○</span><strong>No scheduled activities</strong><p>Add due dates or project activities to place them on this calendar.</p></div>
                @endforelse
            </div>
        </aside>
    </div>
</section>

<section class="card facilitator-assigned-sections">
    <div class="card-heading"><div><h3>Assigned sections</h3><p>Your official schedule appears below each assigned section.</p></div></div>
    <div class="section-summary-grid">
        @forelse($sections as $section)
            <article class="section-summary-card">
                <span class="status-badge active"><i></i>{{ $section->component->code }}</span>
                <h3>{{ $section->name }}</h3>
                <small>{{ $section->enrollments_count }} students · {{ $section->semesterLabel() }} {{ $section->academic_year }}</small>
                @if($section->schedule)
                    <div class="section-facilitator"><span>Official schedule</span><strong>{{ \App\Http\Controllers\Learning\ScheduleController::DAYS[$section->schedule->day_of_week] }} · {{ \Carbon\Carbon::parse($section->schedule->starts_at)->format('g:i A') }}–{{ \Carbon\Carbon::parse($section->schedule->ends_at)->format('g:i A') }}</strong></div>
                @else
                    <div class="section-facilitator"><span>Official schedule</span><strong>Pending</strong></div>
                @endif
            </article>
        @empty
            <div class="empty-state"><strong>No section assignment yet</strong><span>Contact the NSTP Administrator.</span></div>
        @endforelse
    </div>
</section>

<section class="card admin-guide-preview" aria-labelledby="facilitator-guide-preview-title">
    <div class="admin-guide-preview-icon" aria-hidden="true">?</div>
    <div><span class="eyebrow">Facilitator guide</span><h3 id="facilitator-guide-preview-title">Not sure where to begin?</h3><p>Follow the recommended teaching workflow and open each Facilitator tool from one guided overview.</p></div>
    <div class="admin-guide-preview-actions"><button class="primary-button" type="button" data-start-facilitator-tour>Start guided tour <span aria-hidden="true">→</span></button><a href="{{ route('facilitator.community-projects.index') }}">Community projects →</a><a href="{{ route('facilitator.system-guide') }}">Read the full guide</a></div>
</section>
@endsection
