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
        <a class="secondary-button" href="{{ route('admin.users.create') }}">Create user account</a>
    </section>

    <section class="metric-grid" aria-label="System overview">
        <article class="metric-card"><span class="metric-icon blue">♙</span><div><small>Students</small><strong>{{ $studentCount }}</strong><p>Registered accounts</p></div></article>
        <article class="metric-card"><span class="metric-icon green">◎</span><div><small>Facilitators</small><strong>{{ $facilitatorCount }}</strong><p>Registered accounts</p></div></article>
        <article class="metric-card"><span class="metric-icon orange">▤</span><div><small>Active sections</small><strong>{{ $activeSectionCount }}</strong><p>CWTS, LTS, and ROTC</p></div></article>
        <article class="metric-card"><span class="metric-icon violet">✓</span><div><small>System status</small><strong class="status-word">Ready</strong><p>User management is active</p></div></article>
    </section>

    <div class="content-grid">
        <section class="card enrollee-chart-card">
            @php
                $largestComponentCount = max(1, (int) $componentEnrollments->max('count'));
                $totalComponentEnrollees = (int) $componentEnrollments->sum('count');
            @endphp
            <div class="card-heading">
                <div><span class="eyebrow">Enrollment overview</span><h3>Enrollees per NSTP component</h3><p>Unique students with active enrolled status.</p></div>
                <span class="enrollee-total"><strong>{{ number_format($totalComponentEnrollees) }}</strong><small>Total enrollees</small></span>
            </div>
            <div class="enrollee-chart" role="img" aria-label="Bar graph of enrollees per NSTP component">
                @forelse ($componentEnrollments as $component)
                    @php($barWidth = $component['count'] > 0 ? max(8, ($component['count'] / $largestComponentCount) * 100) : 0)
                    <div class="enrollee-bar-row">
                        <div class="enrollee-bar-label">
                            <span><strong>{{ $component['code'] }}</strong><small>{{ $component['name'] }}</small></span>
                            <b>{{ number_format($component['count']) }}</b>
                        </div>
                        <div class="enrollee-bar-track" aria-hidden="true">
                            <span class="component-{{ strtolower($component['code']) }}" style="width: {{ $barWidth }}%"></span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state"><strong>No NSTP components available</strong><span>Component enrollment data will appear here once configured.</span></div>
                @endforelse
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
