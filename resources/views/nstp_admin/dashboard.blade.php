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
        <img class="snapie-character snapie-banner-character" src="{{ asset('images/characters/snapie-wave.webp') }}" alt="SNAPIE mascot waving">
        <span class="workspace-date">{{ now()->format('l') }}<strong>{{ now()->format('M d, Y') }}</strong></span>
    </section>

    <section class="metric-grid" aria-label="NSTP account overview">
        <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>Active students</small><strong>{{ $studentCount }}</strong><p>Registered student accounts</p></div></article>
        <article class="metric-card"><span class="metric-icon green">◎</span><div><small>Facilitators</small><strong>{{ $facilitatorCount }}</strong><p>Active facilitators</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">◇</span><div><small>Coordinators</small><strong>{{ $coordinatorCount }}</strong><p>Active coordinators</p></div></article>
        <article class="metric-card" data-unassigned-student-count="{{ $unassignedStudentCount }}"><span class="metric-icon violet">!</span><div><small>Without component</small><strong>{{ $unassignedStudentCount }}</strong><p>Active students this term</p></div></article>
    </section>

    <section class="card admin-guide-preview" aria-labelledby="nstp-admin-guide-preview-title">
        <div class="admin-guide-preview-icon" aria-hidden="true">?</div>
        <div><span class="eyebrow">NSTP Administrator guide</span><h3 id="nstp-admin-guide-preview-title">Not sure where to begin?</h3><p>Follow the recommended operating order and open each NSTP Administration tool from one guided overview.</p></div>
        <div class="admin-guide-preview-actions"><button class="primary-button" type="button" data-start-nstp-admin-tour>Start guided tour <span aria-hidden="true">→</span></button><a href="{{ route('nstp_admin.community-projects.index') }}">Community projects →</a><a href="{{ route('nstp_admin.project-tasks.index') }}">Task monitoring →</a><a href="{{ route('nstp_admin.evaluations.index') }}">Evaluations →</a><a href="{{ route('nstp_admin.facilitator-requirements.index') }}">Facilitator compliance →</a><a href="{{ route('nstp_admin.honoraria.index') }}">Honorarium administration →</a><a href="{{ route('nstp_admin.engagement-analytics.index') }}">Engagement analytics →</a><a href="{{ route('nstp_admin.system-guide') }}">Read the full guide</a></div>
    </section>

    <section class="component-overview" aria-label="NSTP components">
        <a class="component-card cwts" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'CWTS')?->id]) }}"><x-component-logo component-code="CWTS" class="component-symbol" /><div><span>Civic Welfare Training Service</span><h3>CWTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
        <a class="component-card lts" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'LTS')?->id]) }}"><x-component-logo component-code="LTS" class="component-symbol" /><div><span>Literacy Training Service</span><h3>LTS</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
        <a class="component-card rotc" href="{{ route('nstp_admin.sections.index', ['component_id' => $components->firstWhere('code', 'ROTC')?->id]) }}"><x-component-logo component-code="ROTC" class="component-symbol" /><div><span>Reserve Officers' Training Corps</span><h3>ROTC</h3><p>Configure capacity, sections, student enrollment, and facilitator assignments.</p></div><span class="component-state">Open sectioning →</span></a>
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
