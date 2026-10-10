<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/tau-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/account-create-button.css') }}?v={{ filemtime(public_path('css/account-create-button.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/registration-review.css') }}?v={{ filemtime(public_path('css/registration-review.css')) }}">
    <x-theme-init />
</head>
<body class="admin-body portal-super-admin" data-admin-tour-root>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <button class="sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Collapse sidebar">‹</button>
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <x-system-brand subtitle="Super Admin Portal" />
            </a>

            <div class="role-card">
                <x-user-avatar :user="auth()->user()" />
                <span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></span>
            </div>

            <nav class="main-nav" aria-label="Main navigation">
                <p class="nav-label">Overview</p>
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" data-admin-tour="dashboard">
                    <span class="nav-icon">⌂</span> Dashboard
                </a>
                <p class="nav-label">People & Registration</p>
                @php($managingStudentAccount = request()->routeIs('admin.students.*') || (request()->routeIs('admin.users.edit') && request()->route('user')?->isStudent()) || (request()->routeIs('admin.users.create') && request('role') === 'student'))
                <a class="nav-link {{ request()->routeIs('admin.users.*') && !$managingStudentAccount ? 'active' : '' }}" href="{{ route('admin.users.index') }}" data-admin-tour="staff">
                    <span class="nav-icon">♙</span> Staff Accounts
                </a>
                <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}"><span class="nav-icon">⚿</span> Roles &amp; Permissions</a>
                <a class="nav-link {{ $managingStudentAccount ? 'active' : '' }}" href="{{ route('admin.students.index') }}" data-admin-tour="students"><span class="nav-icon">♟</span> Student Accounts</a>
                <a class="nav-link {{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}" href="{{ route('admin.registrations.index') }}"><span class="nav-icon">▣</span> Registration Reviews</a>
                <p class="nav-label">Program Operations</p>
                <a class="nav-link {{ request()->routeIs('admin.components.*') ? 'active' : '' }}" href="{{ route('admin.components.index') }}" data-admin-tour="components"><span class="nav-icon">◉</span> NSTP Components</a>
                <a class="nav-link {{ request()->routeIs('admin.sections.*', 'admin.sectioning.*') ? 'active' : '' }}" href="{{ route('admin.sections.index') }}" data-admin-tour="sectioning"><span class="nav-icon">▦</span> Sectioning</a>
                <a class="nav-link {{ request()->routeIs('admin.schedules.*') ? 'active' : '' }}" href="{{ route('admin.schedules.index') }}" data-admin-tour="scheduling"><span class="nav-icon">◷</span> Scheduling</a>
                <p class="nav-label">Learning & Assessment</p>
                <a class="nav-link {{ request()->routeIs('admin.attendance.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'attendance') }}" data-admin-tour="attendance"><span class="nav-icon">▣</span> Attendance @if($sidebarPortalNotificationCounts['attendance'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['attendance'] > 99 ? '99+' : $sidebarPortalNotificationCounts['attendance'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('admin.materials.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'materials') }}"><span class="nav-icon">▤</span> Learning Materials @if($sidebarPortalNotificationCounts['materials'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['materials'] > 99 ? '99+' : $sidebarPortalNotificationCounts['materials'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('admin.assessments.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'assessments') }}"><span class="nav-icon">✓</span> Assessments @if($sidebarPortalNotificationCounts['assessments'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['assessments'] > 99 ? '99+' : $sidebarPortalNotificationCounts['assessments'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('admin.grades.*') ? 'active' : '' }}" href="{{ route('admin.grades.index') }}"><span class="nav-icon">◎</span> Grades</a>
                <p class="nav-label">Reports & Records</p>
                <a class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}" data-admin-tour="reports"><span class="nav-icon">◫</span> Reports Center</a>
                <a class="nav-link {{ request()->routeIs('admin.ched-applications.*') ? 'active' : '' }}" href="{{ route('admin.ched-applications.index') }}"><span class="nav-icon">↗</span> CHED Applications</a>
                <a class="nav-link {{ request()->routeIs('admin.document-forms.*') ? 'active' : '' }}" href="{{ route('admin.document-forms.index') }}"><span class="nav-icon">▧</span> Documents &amp; Forms</a>
                <a class="nav-link {{ request()->routeIs('admin.document-reviews.*') ? 'active' : '' }}" href="{{ route('admin.document-reviews.index') }}"><span class="nav-icon">✓</span> Document Reviews</a>
                <a class="nav-link {{ request()->routeIs('admin.review-categories.*') ? 'active' : '' }}" href="{{ route('admin.review-categories.index') }}"><span class="nav-icon">◈</span> Review Categories</a>
                <a class="nav-link {{ request()->routeIs('admin.workflows.*') ? 'active' : '' }}" href="{{ route('admin.workflows.index') }}"><span class="nav-icon">⌘</span> Workflow Rules</a>
                <a class="nav-link {{ request()->routeIs('admin.notification-rules.*') ? 'active' : '' }}" href="{{ route('admin.notification-rules.index') }}"><span class="nav-icon">♢</span> Notification Rules</a>
                <a class="nav-link {{ request()->routeIs('admin.policies.*') ? 'active' : '' }}" href="{{ route('admin.policies.index') }}"><span class="nav-icon">§</span> Policies &amp; Requirements</a>
                <a class="nav-link {{ request()->routeIs('admin.archives.*') ? 'active' : '' }}" href="{{ route('admin.archives.index') }}"><span class="nav-icon">▱</span> Records Archive</a>
                <p class="nav-label">System Administration</p>
                <a class="nav-link {{ request()->routeIs('admin.database-backup.*') ? 'active' : '' }}" href="{{ route('admin.database-backup.index') }}" data-admin-tour="backup"><span class="nav-icon">⇩</span> Database Management</a>
                <a class="nav-link {{ request()->routeIs('admin.system-logs.*') ? 'active' : '' }}" href="{{ route('admin.system-logs.index') }}" data-admin-tour="logs"><span class="nav-icon">☷</span> System Logs</a>
                <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}"><span class="nav-icon">⚙</span> System Settings</a>
                <a class="nav-link {{ request()->routeIs('admin.landing-page.*') ? 'active' : '' }}" href="{{ route('admin.landing-page.edit') }}"><span class="nav-icon">◇</span> Landing Page Editor</a>
                <p class="nav-label">Communication</p>
                <a class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'announcements') }}"><span class="nav-icon">◫</span> Announcements @if($sidebarPortalNotificationCounts['announcements'])<span class="nav-count">{{ $sidebarPortalNotificationCounts['announcements'] > 99 ? '99+' : $sidebarPortalNotificationCounts['announcements'] }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('admin.messages.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'messages') }}"><span class="nav-icon">◇</span> Messages @if($sidebarUnreadMessageCount > 0)<span class="nav-count" data-unread-message-count="{{ $sidebarUnreadMessageCount }}">{{ $sidebarUnreadMessageCount > 99 ? '99+' : $sidebarUnreadMessageCount }}</span>@endif</a>
                <a class="nav-link {{ request()->routeIs('ai-assistant.*') ? 'active' : '' }}" href="{{ route('ai-assistant.index') }}" data-admin-tour="ai"><span class="nav-icon">✦</span> AI Assistant</a>
                <p class="nav-label">Account</p>
                <a class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.profile.edit') }}">
                    <span class="nav-icon">⚙</span> Profile & Security
                </a>
                <a class="nav-link {{ request()->routeIs('admin.system-guide') ? 'active' : '' }}" href="{{ route('admin.system-guide') }}" data-admin-tour="guide">
                    <span class="nav-icon">?</span> System Guide
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
                <div data-admin-tour-page-title>
                    <small>TAU NSTP / Super Admin</small>
                    <h1>@yield('page-title', 'Dashboard')</h1>
                </div>
                <x-notification-bell />
                <x-theme-toggle />
                <button class="admin-tour-topbar-button" type="button" data-start-admin-tour><span aria-hidden="true">?</span> Guided Tour</button>
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

            <footer class="app-footer">© {{ date('Y') }} Tarlac Agricultural University · National Service Training Program</footer>
        </main>
    </div>
    <x-ai-chat-widget />
    <script src="{{ asset('js/sidebar.js') }}"></script>
    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/table-sort.js') }}?v={{ filemtime(public_path('js/table-sort.js')) }}"></script>
    <script src="{{ asset('js/admin-tour.js') }}?v={{ filemtime(public_path('js/admin-tour.js')) }}"></script>
</body>
</html>
