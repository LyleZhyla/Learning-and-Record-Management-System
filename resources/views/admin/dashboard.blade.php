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

    <section class="card admin-guide-preview" aria-labelledby="admin-guide-preview-title">
        <div class="admin-guide-preview-icon" aria-hidden="true">?</div>
        <div>
            <span class="eyebrow">New administrator guide</span>
            <h3 id="admin-guide-preview-title">Not sure where to begin?</h3>
            <p>Follow the recommended setup order, understand every account role, and open each Super Admin tool from one guided overview.</p>
        </div>
        <div class="admin-guide-preview-actions">
            <button class="primary-button" type="button" data-start-admin-tour>Start guided tour <span aria-hidden="true">→</span></button>
            <a href="{{ route('admin.community-projects.index') }}">Community projects →</a>
            <a href="{{ route('admin.project-tasks.index') }}">Task monitoring →</a>
            <a href="{{ route('admin.evaluations.index') }}">Evaluations →</a>
            <a href="{{ route('admin.facilitator-requirements.index') }}">Facilitator compliance →</a>
            <a href="{{ route('admin.engagement-analytics.index') }}">Engagement analytics →</a>
            <a href="{{ route('admin.system-guide') }}">Read the full guide</a>
        </div>
    </section>

    <section class="report-analytics-section dashboard-analytics" aria-labelledby="dashboard-analytics-title">
        <div class="report-analytics-heading">
            <div><span class="eyebrow">System operations</span><h3 id="dashboard-analytics-title">Platform activity and access overview</h3><p>Institution-wide monitoring for {{ $academicTerm }}.</p></div>
        </div>
        <div class="report-analytics-grid report-analytics-grid-single">
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

            <article class="card report-chart-card account-access-card">
                <div class="report-chart-heading">
                    <div><span class="eyebrow">Access governance</span><h4>Account access overview</h4><p>Active and inactive platform accounts grouped by system role.</p></div>
                    <span class="report-chart-total green"><strong>{{ number_format($activeAccountCount) }}</strong><small>Active accounts</small></span>
                </div>
                <div class="account-access-summary" aria-label="{{ number_format($activeAccountCount) }} active and {{ number_format($inactiveAccountCount) }} inactive accounts">
                    <span><i class="active" aria-hidden="true"></i>Active <strong>{{ number_format($activeAccountCount) }}</strong></span>
                    <span><i class="inactive" aria-hidden="true"></i>Inactive <strong>{{ number_format($inactiveAccountCount) }}</strong></span>
                </div>
                <div class="account-access-chart" role="list" aria-label="Account access status by system role">
                    @foreach ($accountAccessOverview as $accountRole)
                        <article class="account-access-row" role="listitem" aria-label="{{ $accountRole['label'] }}: {{ $accountRole['active'] }} active, {{ $accountRole['inactive'] }} inactive">
                            <div class="account-access-role"><strong>{{ $accountRole['label'] }}</strong><small>{{ number_format($accountRole['total']) }} total</small></div>
                            <div class="account-access-track" aria-hidden="true">
                                @if ($accountRole['active'] > 0)<span class="active" style="width: {{ $accountRole['active_percentage'] }}%"></span>@endif
                                @if ($accountRole['inactive'] > 0)<span class="inactive" style="width: {{ $accountRole['inactive_percentage'] }}%"></span>@endif
                            </div>
                            <div class="account-access-counts"><strong>{{ number_format($accountRole['active']) }}</strong><span>/</span><strong>{{ number_format($accountRole['inactive']) }}</strong></div>
                        </article>
                    @endforeach
                </div>
            </article>
        </div>
    </section>
@endsection
