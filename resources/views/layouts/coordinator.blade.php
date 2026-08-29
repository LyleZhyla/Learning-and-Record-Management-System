<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Coordinator') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/smart-nstp-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="admin-body"><div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="{{ route('coordinator.dashboard') }}"><img class="brand-logo" src="{{ asset('images/smart-nstp-logo-160.png') }}" alt="Smart NSTP logo"><span><strong>Smart NSTP</strong><small>Coordinator Portal</small></span></a>
        <div class="role-card"><span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span><span><strong>{{ auth()->user()->name }}</strong><small>NSTP Coordinator</small></span></div>
        <nav class="main-nav" aria-label="Coordinator navigation">
            <p class="nav-label">Overview</p>
            <a class="nav-link {{ request()->routeIs('coordinator.dashboard') ? 'active' : '' }}" href="{{ route('coordinator.dashboard') }}"><span class="nav-icon">⌂</span> Dashboard</a>
            <p class="nav-label">Monitoring</p>
            <a class="nav-link {{ request()->routeIs('coordinator.components.*') ? 'active' : '' }}" href="{{ route('coordinator.components.index') }}"><span class="nav-icon">◉</span> Components</a>
            <a class="nav-link {{ request()->routeIs('coordinator.sections.*') ? 'active' : '' }}" href="{{ route('coordinator.sections.index') }}"><span class="nav-icon">▦</span> Sections & Facilitators</a>
            <a class="nav-link {{ request()->routeIs('coordinator.attendance.*') ? 'active' : '' }}" href="{{ route('coordinator.attendance.index') }}"><span class="nav-icon">▣</span> Attendance</a>
            <a class="nav-link {{ request()->routeIs('coordinator.performance.*') ? 'active' : '' }}" href="{{ route('coordinator.performance.index') }}"><span class="nav-icon">◎</span> Performance & Grades</a>
            <p class="nav-label">Account</p>
            <a class="nav-link {{ request()->routeIs('coordinator.profile.*') ? 'active' : '' }}" href="{{ route('coordinator.profile.edit') }}"><span class="nav-icon">⚙</span> Profile & Security</a>
        </nav>
        <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button type="submit" class="nav-link logout"><span class="nav-icon">↪</span> Sign out</button></form>
    </aside>
    <main class="main-content"><header class="topbar"><button class="menu-button" type="button" aria-label="Toggle navigation" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button><div><small>Smart NSTP / Coordinator</small><h1>@yield('page-title', 'Dashboard')</h1></div><div class="topbar-status"><span></span> Read-only monitoring</div></header>@if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif @if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif @yield('content')<footer class="app-footer">© {{ date('Y') }} Smart NSTP Management and AI-Integrated Platform</footer></main>
</div></body></html>
