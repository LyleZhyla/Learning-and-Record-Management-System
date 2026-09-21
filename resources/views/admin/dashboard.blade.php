@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <section class="welcome-banner">
        <div>
            <span class="eyebrow">Super Administrator workspace</span>
            <h2>Good day, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
            <p>Manage system access across five NSTP roles and monitor the accounts registered on the platform.</p>
        </div>
        <img class="snapie-character snapie-banner-character" src="{{ asset('images/characters/snapie-wave.webp') }}" alt="SNAPIE mascot waving">
        <a class="secondary-button create-account-button" href="{{ route('admin.users.create') }}">Create staff account</a>
    </section>

    <section class="metric-grid" aria-label="System overview">
        <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>Students</small><strong>{{ $studentCount }}</strong><p>Registered accounts</p></div></article>
        <article class="metric-card"><span class="metric-icon green">◎</span><div><small>Facilitators</small><strong>{{ $facilitatorCount }}</strong><p>Registered accounts</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">▤</span><div><small>Active sections</small><strong>{{ $activeSectionCount }}</strong><p>CWTS, LTS, and ROTC</p></div></article>
        <article class="metric-card" data-unassigned-student-count="{{ $unassignedStudentCount }}"><span class="metric-icon violet">!</span><div><small>Without component</small><strong>{{ $unassignedStudentCount }}</strong><p>Active students this term</p></div></article>
    </section>

    <section class="report-analytics-section dashboard-analytics" aria-labelledby="dashboard-analytics-title">
        <div class="report-analytics-heading">
            <div><span class="eyebrow">Current-term analytics</span><h3 id="dashboard-analytics-title">Attendance and enrollment overview</h3><p>{{ $academicTerm }} institution-wide activity.</p></div>
        </div>
        <div class="report-analytics-grid">
            <article class="card report-chart-card">
                <div class="report-chart-heading">
                    <div><span class="eyebrow">Attendance trend</span><h4>Daily attendance rate</h4><p>Present and late records across the latest 12 attendance dates.</p></div>
                    <span class="report-chart-total"><strong>{{ number_format($attendanceChart['average_rate'], 1) }}%</strong><small>Average rate</small></span>
                </div>
                @if($attendanceChart['points']->isNotEmpty())
                    <div class="attendance-line-chart">
                        <svg viewBox="0 0 {{ $attendanceChart['width'] }} {{ $attendanceChart['height'] }}" role="img" aria-labelledby="dashboard-attendance-title dashboard-attendance-description">
                            <title id="dashboard-attendance-title">Attendance rate line graph</title>
                            <desc id="dashboard-attendance-description">Daily percentage of present and late attendance records during the current academic term.</desc>
                            @foreach($attendanceChart['ticks'] as $tick)
                                <line class="attendance-grid-line" x1="{{ $attendanceChart['left'] }}" y1="{{ $tick['y'] }}" x2="{{ $attendanceChart['right'] }}" y2="{{ $tick['y'] }}" />
                                <text class="attendance-axis-label" x="{{ $attendanceChart['left'] - 10 }}" y="{{ $tick['y'] + 4 }}" text-anchor="end">{{ $tick['value'] }}%</text>
                            @endforeach
                            <polygon class="attendance-area" points="{{ $attendanceChart['area_points'] }}" />
                            <polyline class="attendance-line" points="{{ $attendanceChart['point_string'] }}" />
                            @foreach($attendanceChart['points'] as $point)
                                <circle class="attendance-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5"><title>{{ $point['label'] }}: {{ number_format($point['rate'], 1) }}% ({{ $point['attended'] }} of {{ $point['total'] }})</title></circle>
                                <text class="attendance-date-label" x="{{ $point['x'] }}" y="238" text-anchor="middle">{{ $point['label'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                @else
                    <div class="empty-state report-chart-empty"><strong>No attendance data yet</strong><span>Current-term attendance trends will appear after records are created.</span></div>
                @endif
            </article>

            <article class="card report-chart-card enrollee-chart-card">
                <div class="report-chart-heading">
                    <div><span class="eyebrow">Enrollment overview</span><h4>Enrollees per component and ROTC category</h4><p>Current-term enrolled students and pending ROTC approvals.</p></div>
                    <span class="report-chart-total green"><strong>{{ number_format($componentEnrollmentTotal) }}</strong><small>Total selections</small></span>
                </div>
                <div class="enrollee-chart" role="list" aria-label="Vertical bar graph of students per NSTP component and ROTC category" data-chart-orientation="vertical" style="--chart-columns: {{ max(1, $componentEnrollments->count()) }}">
                    @forelse ($componentEnrollments as $component)
                        <article class="enrollee-column" role="listitem" aria-label="{{ $component['code'] }}: {{ number_format($component['count']) }} enrollees">
                            <div class="enrollee-column-track" aria-hidden="true">
                                <span class="component-{{ strtolower($component['code']) }}" style="height: {{ $component['percentage'] }}%"><b>{{ number_format($component['count']) }}</b></span>
                            </div>
                            <div class="enrollee-column-label"><strong>{{ $component['code'] }}</strong><small>{{ $component['name'] }}</small></div>
                        </article>
                    @empty
                        <div class="empty-state"><strong>No NSTP components available</strong><span>Component enrollment data will appear here once configured.</span></div>
                    @endforelse
                </div>
            </article>
        </div>
    </section>
@endsection
