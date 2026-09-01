<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <x-theme-init />
</head>
<body class="admin-body">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <button class="sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Collapse sidebar">‹</button>
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <img class="brand-logo brand-logo-compact" src="{{ asset('images/snapie-logo-160.png') }}" alt="SNAPIE logo">
                <img class="brand-logo-dark" src="{{ asset('images/snapie-landscape-dark.png') }}" alt="SNAPIE — Smart NSTP Management and AI-Integrated Platform">
                <span><strong>Smart NSTP</strong><small>Management Platform</small></span>
            </a>

            <div class="role-card">
                <x-user-avatar :user="auth()->user()" />
                <span><strong>{{ auth()->user()->name }}</strong><small>Super Administrator</small></span>
            </div>

            <nav class="main-nav" aria-label="Main navigation">
                <p class="nav-label">Overview</p>
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <span class="nav-icon">⌂</span> Dashboard
                </a>
                <p class="nav-label">Administration</p>
                @php($managingStudentAccount = request()->routeIs('admin.students.*') || (request()->routeIs('admin.users.edit') && request()->route('user')?->isStudent()) || (request()->routeIs('admin.users.create') && request('role') === 'student'))
                <a class="nav-link {{ request()->routeIs('admin.users.*') && !$managingStudentAccount ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <span class="nav-icon">♙</span> Staff Accounts
                </a>
                <a class="nav-link {{ $managingStudentAccount ? 'active' : '' }}" href="{{ route('admin.students.index') }}"><span class="nav-icon">♟</span> Student Accounts</a>
                <a class="nav-link {{ request()->routeIs('admin.components.*', 'admin.sections.*', 'admin.sectioning.*') ? 'active' : '' }}" href="{{ route('admin.sections.index') }}"><span class="nav-icon">▦</span> Sectioning</a>
                <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><span class="nav-icon">◫</span> Reports</a>
                <a class="nav-link {{ request()->routeIs('admin.database-backup.*') ? 'active' : '' }}" href="{{ route('admin.database-backup.index') }}"><span class="nav-icon">⇩</span> Database Backup</a>
                <a class="nav-link {{ request()->routeIs('admin.system-logs.*') ? 'active' : '' }}" href="{{ route('admin.system-logs.index') }}"><span class="nav-icon">☷</span> System Logs</a>
                <a class="nav-link {{ request()->routeIs('admin.archives.*') ? 'active' : '' }}" href="{{ route('admin.archives.index') }}"><span class="nav-icon">▱</span> Records Archive</a>
                <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}"><span class="nav-icon">⚙</span> System Settings</a>
                <p class="nav-label">Attendance & Learning</p>
                <a class="nav-link {{ request()->routeIs('admin.attendance.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'attendance') }}"><span class="nav-icon">▣</span> Attendance @if($sidebarPortalNotificationCounts['attendance'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['attendance'] > 99 ? '99+' : $sidebarPortalNotificationCounts['attendance'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('admin.materials.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'materials') }}"><span class="nav-icon">▤</span> Learning Materials @if($sidebarPortalNotificationCounts['materials'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['materials'] > 99 ? '99+' : $sidebarPortalNotificationCounts['materials'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('admin.assessments.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'assessments') }}"><span class="nav-icon">✓</span> Assessments @if($sidebarPortalNotificationCounts['assessments'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['assessments'] > 99 ? '99+' : $sidebarPortalNotificationCounts['assessments'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('admin.grades.*') ? 'active' : '' }}" href="{{ route('admin.grades.index') }}"><span class="nav-icon">◎</span> Grades</a>
                <p class="nav-label">Communication</p>
                <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'announcements') }}"><span class="nav-icon">◫</span> Announcements @if($sidebarPortalNotificationCounts['announcements'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['announcements'] > 99 ? '99+' : $sidebarPortalNotificationCounts['announcements'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('ai-assistant.*') ? 'active' : '' }}" href="{{ route('ai-assistant.index') }}"><span class="nav-icon">✦</span> AI Assistant</a>
                <p class="nav-label">Account</p>
                <a class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.profile.edit') }}">
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
                    <small>Smart NSTP / Super Admin</small>
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
                    This account is using a temporary password. <a href="{{ route('admin.profile.edit') }}#password">Change it now</a>.
                </div>
            @endif

            @yield('content')

            <footer class="app-footer">© {{ date('Y') }} Smart NSTP Management and AI-Integrated Platform</footer>
        </main>
    </div>
    <x-ai-chat-widget />
    <script src="{{ asset('js/sidebar.js') }}"></script>
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
