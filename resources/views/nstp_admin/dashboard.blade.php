@extends('layouts.nstp-admin')

@section('title', 'NSTP Admin Dashboard')
@section('page-title', 'NSTP Admin Dashboard')

@section('content')
    <section class="welcome-banner nstp-welcome">
        <div>
            <span class="eyebrow">Institution-wide operations</span>
            <h2>Good day, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
            <p>Monitor NSTP participation and prepare CWTS, LTS, and ROTC operations from one centralized workspace.</p>
        </div>
        <span class="workspace-date">{{ now()->format('l') }}<strong>{{ now()->format('M d, Y') }}</strong></span>
    </section>

    <section class="metric-grid" aria-label="NSTP account overview">
        <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>Active students</small><strong>{{ $studentCount }}</strong><p>Registered student accounts</p></div></article>
        <article class="metric-card"><span class="metric-icon green">◎</span><div><small>Facilitators</small><strong>{{ $facilitatorCount }}</strong><p>Active facilitators</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">◇</span><div><small>Coordinators</small><strong>{{ $coordinatorCount }}</strong><p>Active coordinators</p></div></article>
        <article class="metric-card" data-unassigned-student-count="{{ $unassignedStudentCount }}"><span class="metric-icon violet">!</span><div><small>Without component</small><strong>{{ $unassignedStudentCount }}</strong><p>Active students this term</p></div></article>
    </section>

    <section class="component-overview" aria-label="NSTP components">
        <a class="component-card cwts" href="{{ route('nstp_admin.components.index') }}"><div class="component-symbol">C</div><div><span>Civic Welfare Training Service</span><h3>CWTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Manage component →</span></a>
        <a class="component-card lts" href="{{ route('nstp_admin.components.index') }}"><div class="component-symbol">L</div><div><span>Literacy Training Service</span><h3>LTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Manage component →</span></a>
        <a class="component-card rotc" href="{{ route('nstp_admin.components.index') }}"><div class="component-symbol">R</div><div><span>Reserve Officers' Training Corps</span><h3>ROTC</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Manage component →</span></a>
    </section>

    <div class="content-grid nstp-content-grid">
        <section class="card">
            <div class="card-heading"><div><span class="eyebrow">Latest registrations</span><h3>Recent NSTP accounts</h3><p>Recently added students, facilitators, and coordinators.</p></div><span class="pill">{{ $recentAccounts->count() }} shown</span></div>
            <div class="compact-user-list">
                @forelse ($recentAccounts as $account)
                    <div class="compact-user-row">
                        <span class="table-avatar">{{ strtoupper(substr($account->name, 0, 1)) }}</span>
                        <div><strong>{{ $account->name }}</strong><small>{{ $account->email }}</small></div>
                        <span class="role-badge role-{{ $account->role }}">{{ $account->roleLabel() }}</span>
                        <span class="status-badge {{ $account->status }}"><i></i>{{ $account->statusLabel() }}</span>
                    </div>
                @empty
                    <div class="empty-state"><strong>No operational accounts yet</strong><span>Accounts created by the Super Admin will appear here.</span></div>
                @endforelse
            </div>
        </section>

        <aside class="card">
            <div class="card-heading"><div><span class="eyebrow">Module readiness</span><h3>Operations checklist</h3></div></div>
            <div class="readiness-list">
                <div><span class="check ready">✓</span><p><strong>NSTP Admin access</strong><small>Secure role-based dashboard</small></p></div>
                <div><span class="check ready">✓</span><p><strong>Components & sections</strong><small>CWTS, LTS, and ROTC configuration</small></p></div>
                <div><span class="check ready">✓</span><p><strong>Student assignment</strong><small>Automated component and section placement</small></p></div>
                <div><span class="check ready">✓</span><p><strong>Operational reports</strong><small>Masterlists, summaries, CSV, and print output</small></p></div>
                <div><span class="check ready">✓</span><p><strong>Announcements</strong><small>Targeted messages by audience and component</small></p></div>
            </div>
        </aside>
    </div>
@endsection
