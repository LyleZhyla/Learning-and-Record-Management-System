@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <section class="welcome-banner">
        <div>
            <span class="eyebrow">Super Administrator workspace</span>
            <h2>Good day, {{ explode(' ', auth()->user()->name)[0] }}.</h2>
            <p>Your administration foundation is ready. The next modules can now be added without changing the authentication and access-control layer.</p>
        </div>
        <a class="secondary-button" href="{{ route('admin.profile.edit') }}">Review account security</a>
    </section>

    <section class="metric-grid" aria-label="System overview">
        <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>Students</small><strong>0</strong><p>Awaiting student module</p></div></article>
        <article class="metric-card"><span class="metric-icon green">◎</span><div><small>Facilitators</small><strong>0</strong><p>Awaiting facilitator module</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">▤</span><div><small>Active sections</small><strong>0</strong><p>CWTS, LTS, and ROTC</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">✓</span><div><small>System status</small><strong class="status-word">Ready</strong><p>Authentication is active</p></div></article>
    </section>

    <div class="content-grid">
        <section class="card">
            <div class="card-heading"><div><span class="eyebrow">Development roadmap</span><h3>Core management modules</h3></div><span class="pill">Foundation complete</span></div>
            <div class="module-list">
                <div class="module-item"><span class="step done">1</span><div><strong>Super Admin account</strong><p>Secure login, role protection, profile, password change, and logout</p></div><span class="state done">Complete</span></div>
                <div class="module-item"><span class="step">2</span><div><strong>User and role management</strong><p>Create coordinators, facilitators, and student accounts</p></div><span class="state">Next</span></div>
                <div class="module-item"><span class="step">3</span><div><strong>NSTP components and sections</strong><p>Configure CWTS, LTS, ROTC, and automated sectioning</p></div><span class="state">Planned</span></div>
                <div class="module-item"><span class="step">4</span><div><strong>Attendance and learning</strong><p>QR monitoring, materials, assessments, and grades</p></div><span class="state">Planned</span></div>
            </div>
        </section>

        <aside class="card account-summary">
            <div class="card-heading"><div><span class="eyebrow">Current session</span><h3>Account overview</h3></div></div>
            <div class="large-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            <h4>{{ auth()->user()->name }}</h4>
            <p>{{ auth()->user()->email }}</p>
            <dl>
                <div><dt>Role</dt><dd>Super Administrator</dd></div>
                <div><dt>Status</dt><dd><span class="online-dot"></span> Active</dd></div>
                <div><dt>Last sign in</dt><dd>{{ auth()->user()->last_login_at?->format('M d, Y · h:i A') ?? 'First session' }}</dd></div>
            </dl>
            <a class="text-link" href="{{ route('admin.profile.edit') }}">Manage profile and security →</a>
        </aside>
    </div>
@endsection
