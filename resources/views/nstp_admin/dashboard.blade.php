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
        <a class="component-card cwts" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'CWTS')?->id]) }}"><div class="component-symbol">C</div><div><span>Civic Welfare Training Service</span><h3>CWTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
        <a class="component-card lts" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'LTS')?->id]) }}"><div class="component-symbol">L</div><div><span>Literacy Training Service</span><h3>LTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
        <a class="component-card rotc" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'ROTC')?->id]) }}"><div class="component-symbol">R</div><div><span>Reserve Officers' Training Corps</span><h3>ROTC</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
    </section>

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
@endsection
