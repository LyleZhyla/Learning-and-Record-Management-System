<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Coordinator') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}"><x-theme-init />
</head>
<body class="admin-body"><div class="app-shell">
    <aside class="sidebar" id="sidebar"><button class="sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Collapse sidebar">‹</button>
        <a class="brand" href="{{ route('coordinator.dashboard') }}"><img class="brand-logo" src="{{ asset('images/snapie-logo-160.png') }}" alt="SNAPIE logo"><span><strong>Smart NSTP</strong><small>Coordinator Portal</small></span></a>
        <div class="role-card"><x-user-avatar :user="auth()->user()" /><span><strong>{{ auth()->user()->name }}</strong><small>NSTP Coordinator</small></span></div>
        <nav class="main-nav" aria-label="Coordinator navigation">
            <p class="nav-label">Overview</p>
            <a class="nav-link {{ request()->routeIs('coordinator.dashboard') ? 'active' : '' }}" href="{{ route('coordinator.dashboard') }}"><span class="nav-icon">⌂</span> Dashboard</a>
            <p class="nav-label">NSTP Operations</p>
            <a class="nav-link {{ request()->routeIs('coordinator.components.*') ? 'active' : '' }}" href="{{ route('coordinator.components.index') }}"><span class="nav-icon">◉</span> Components</a>
            <a class="nav-link {{ request()->routeIs('coordinator.accounts.*') ? 'active' : '' }}" href="{{ route('coordinator.accounts.index') }}"><span class="nav-icon">♙</span> Facilitators & Students</a>
            <a class="nav-link {{ request()->routeIs('coordinator.sections.*') ? 'active' : '' }}" href="{{ route('coordinator.sections.index') }}"><span class="nav-icon">▦</span> Sections & Facilitators</a>
            @if(auth()->user()->nstpComponent?->code === 'ROTC')<a class="nav-link {{ request()->routeIs('coordinator.rotc-approvals.*') ? 'active' : '' }}" href="{{ route('coordinator.rotc-approvals.index') }}"><span class="nav-icon">✓</span> ROTC Approvals</a>@endif
            <p class="nav-label">Attendance & Grading</p>
            <a class="nav-link {{ request()->routeIs('coordinator.attendance.*') ? 'active' : '' }}" href="{{ route('coordinator.attendance.index') }}"><span class="nav-icon">▣</span> Attendance</a>
            <a class="nav-link {{ request()->routeIs('coordinator.omr.*') ? 'active' : '' }}" href="{{ route('coordinator.omr.index') }}"><span class="nav-icon">▦</span> Answer Sheet Scanner</a>
            <a class="nav-link {{ request()->routeIs('coordinator.performance.*') ? 'active' : '' }}" href="{{ route('coordinator.performance.index') }}"><span class="nav-icon">◎</span> Performance & Grades</a>
            <a class="nav-link {{ request()->routeIs('coordinator.grades.*') ? 'active' : '' }}" href="{{ route('coordinator.grades.index') }}"><span class="nav-icon">✎</span> Grading Setup</a>
            <p class="nav-label">Reports</p>
            <a class="nav-link {{ request()->routeIs('coordinator.reports.*') ? 'active' : '' }}" href="{{ route('coordinator.reports.index') }}"><span class="nav-icon">▤</span> Reports Center</a>
            <p class="nav-label">Communication</p>
            <a class="nav-link {{ request()->routeIs('coordinator.announcements.*') ? 'active' : '' }}" href="{{ route('coordinator.announcements.index') }}"><span class="nav-icon">◫</span> Announcements</a>
            <p class="nav-label">Account</p>
            <a class="nav-link {{ request()->routeIs('coordinator.profile.*') ? 'active' : '' }}" href="{{ route('coordinator.profile.edit') }}"><span class="nav-icon">⚙</span> Profile & Security</a>
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button type="submit" class="nav-link logout"><span class="nav-icon">↪</span> Sign out</button></form>
    </aside>
    <main class="main-content"><header class="topbar"><button class="menu-button" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">☰</button><div><small>Smart NSTP / Coordinator</small><h1>@yield('page-title', 'Dashboard')</h1></div><x-notification-bell /><x-theme-toggle /><div class="topbar-status"><span></span> Monitoring & QR scanning</div></header>@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif @if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif @yield('content')<footer class="app-footer">© {{ date('Y') }} Smart NSTP Management and AI-Integrated Platform</footer></main>
</div><script src="{{ asset('js/sidebar.js') }}"></script><script src="{{ asset('js/theme.js') }}"></script></body></html>
