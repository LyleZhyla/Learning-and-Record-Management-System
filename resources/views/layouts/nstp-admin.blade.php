<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'NSTP Admin') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <x-theme-init />
</head>
<body class="admin-body">
    <div class="app-shell">
        <aside class="sidebar nstp-sidebar" id="sidebar">
            <button class="sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Collapse sidebar">‹</button>
            <a class="brand" href="{{ route('nstp_admin.dashboard') }}">
                <img class="brand-logo" src="{{ asset('images/snapie-logo-160.png') }}" alt="SNAPIE logo">
                <span><strong>Smart NSTP</strong><small>Management Platform</small></span>
            </a>

            <div class="role-card">
                <x-user-avatar :user="auth()->user()" class="nstp-avatar" />
                <span><strong>{{ auth()->user()->name }}</strong><small>NSTP Administrator</small></span>
            </div>

            <nav class="main-nav" aria-label="NSTP Admin navigation">
                <p class="nav-label">Overview</p>
                <a class="nav-link {{ request()->routeIs('nstp_admin.dashboard') ? 'active' : '' }}" href="{{ route('nstp_admin.dashboard') }}">
                    <span class="nav-icon">⌂</span> Dashboard
                </a>
                <p class="nav-label">NSTP Operations</p>
                @php($managingStudentAccount = request()->routeIs('nstp_admin.students.*') || (request()->routeIs('nstp_admin.accounts.show') && request()->route('user')?->isStudent()))
                <a class="nav-link {{ request()->routeIs('nstp_admin.accounts.*') && !$managingStudentAccount ? 'active' : '' }}" href="{{ route('nstp_admin.accounts.index') }}"><span class="nav-icon">♙</span> Staff Accounts</a>
                <a class="nav-link {{ $managingStudentAccount ? 'active' : '' }}" href="{{ route('nstp_admin.students.index') }}"><span class="nav-icon">♟</span> Student Accounts</a>
                <a class="nav-link {{ request()->routeIs('nstp_admin.components.*') ? 'active' : '' }}" href="{{ route('nstp_admin.components.index') }}"><span class="nav-icon">◉</span> Components</a>
                <a class="nav-link {{ request()->routeIs('nstp_admin.sections.*', 'nstp_admin.sectioning.*') ? 'active' : '' }}" href="{{ route('nstp_admin.sections.index') }}"><span class="nav-icon">▦</span> Sectioning</a>
                <p class="nav-label">Attendance & Learning</p>
                <a class="nav-link {{ request()->routeIs('nstp_admin.attendance.*') ? 'active' : '' }}" href="{{ route('nstp_admin.attendance.index') }}"><span class="nav-icon">▣</span> Attendance</a>
                <a class="nav-link {{ request()->routeIs('nstp_admin.materials.*') ? 'active' : '' }}" href="{{ route('nstp_admin.materials.index') }}"><span class="nav-icon">▤</span> Learning Materials</a>
                <a class="nav-link {{ request()->routeIs('nstp_admin.assessments.*') ? 'active' : '' }}" href="{{ route('nstp_admin.assessments.index') }}"><span class="nav-icon">✓</span> Assessments</a>
                <a class="nav-link {{ request()->routeIs('nstp_admin.grades.*') ? 'active' : '' }}" href="{{ route('nstp_admin.grades.index') }}"><span class="nav-icon">◎</span> Grades</a>
                <a class="nav-link {{ request()->routeIs('nstp_admin.reports.*') ? 'active' : '' }}" href="{{ route('nstp_admin.reports.index') }}"><span class="nav-icon">▤</span> Reports</a>
                <p class="nav-label">Communication</p>
                <a class="nav-link {{ request()->routeIs('nstp_admin.announcements.*') ? 'active' : '' }}" href="{{ route('nstp_admin.announcements.index') }}"><span class="nav-icon">◫</span> Announcements</a>
                <a class="nav-link {{ request()->routeIs('ai-assistant.*') ? 'active' : '' }}" href="{{ route('ai-assistant.index') }}"><span class="nav-icon">✦</span> AI Assistant</a>
                <p class="nav-label">Account</p>
                <a class="nav-link {{ request()->routeIs('nstp_admin.profile.*') ? 'active' : '' }}" href="{{ route('nstp_admin.profile.edit') }}">
                    <span class="nav-icon">⚙</span> Profile & Security
                </a>
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                @csrf
                <button type="submit" class="nav-link logout"><span class="nav-icon">↪</span> Sign out</button>
            </form>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <button class="menu-button" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">☰</button>
                <div>
                    <small>Smart NSTP / NSTP Admin</small>
                    <h1>@yield('page-title', 'Dashboard')</h1>
                </div>
                <x-notification-bell />
                <x-theme-toggle />
                <div class="topbar-status"><span></span> System online</div>
            </header>

            @if (session('status'))
                <div class="alert success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert danger" role="alert">{{ $errors->first() }}</div>
            @endif

            @if (auth()->user()->must_change_password)
                <div class="alert warning">
                    This account is using a temporary password. <a href="{{ route('nstp_admin.profile.edit') }}#password">Change it now</a>.
                </div>
            @endif

            @yield('content')

            <footer class="app-footer">© {{ date('Y') }} Smart NSTP Management and AI-Integrated Platform</footer>
        </main>
    </div>
    <script src="{{ asset('js/sidebar.js') }}"></script>
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
