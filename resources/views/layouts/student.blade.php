<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Student') · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/branding/tau-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <x-theme-init />
</head>
<body class="admin-body portal-student" data-student-tour-root>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <button class="sidebar-toggle" type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Collapse sidebar">‹</button>
        <a class="brand" href="{{ route('student.dashboard') }}">
            <x-system-brand subtitle="Student Learning Portal" />
        </a>
        <div class="role-card"><x-user-avatar :user="auth()->user()" /><span><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></span></div>

        <nav class="main-nav" aria-label="Student navigation">
            <p class="nav-label">Overview</p>
            <a class="nav-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}" data-student-tour="dashboard"><span class="nav-icon">⌂</span> Dashboard</a>
            <p class="nav-label">Enrollment</p>
            @permission('student.component')<a class="nav-link {{ request()->routeIs('student.component.*') ? 'active' : '' }}" href="{{ route('student.component.edit') }}" data-student-tour="component"><span class="nav-icon">◈</span> NSTP Selection</a>@endpermission
            @permission('student.documents')<a class="nav-link {{ request()->routeIs('student.documents.*') ? 'active' : '' }}" href="{{ route('student.documents.index') }}"><span class="nav-icon">▧</span> My Documents &amp; Forms</a>@endpermission

            <p class="nav-label">Learning</p>
            @permission('learning.attendance')<a class="nav-link {{ request()->routeIs('student.attendance.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'attendance') }}" data-student-tour="attendance"><span class="nav-icon">▣</span> Attendance @if($sidebarNotificationCounts['attendance'] > 0)<span class="nav-count" data-attendance-notification-count="{{ $sidebarNotificationCounts['attendance'] }}" aria-label="{{ $sidebarNotificationCounts['attendance'] }} unread attendance notifications">{{ $sidebarNotificationCounts['attendance'] > 99 ? '99+' : $sidebarNotificationCounts['attendance'] }}</span>@endif</a>@endpermission
            @permission('learning.materials')<a class="nav-link {{ request()->routeIs('student.materials.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'materials') }}" data-student-tour="materials"><span class="nav-icon">▤</span> Materials @if($sidebarNotificationCounts['materials'] > 0)<span class="nav-count" data-material-notification-count="{{ $sidebarNotificationCounts['materials'] }}" aria-label="{{ $sidebarNotificationCounts['materials'] }} unread material notifications">{{ $sidebarNotificationCounts['materials'] > 99 ? '99+' : $sidebarNotificationCounts['materials'] }}</span>@endif</a>@endpermission
            @permission('ai.use')<a class="nav-link {{ request()->routeIs('student.recommendations.*') ? 'active' : '' }}" href="{{ route('student.recommendations.index') }}" data-student-tour="recommendations"><span class="nav-icon">✦</span> AI Learning Recommendations</a>
            <a class="nav-link {{ request()->routeIs('student.proposal-guide.*') ? 'active' : '' }}" href="{{ route('student.proposal-guide.index') }}" data-student-tour="proposal"><span class="nav-icon">✎</span> Proposal Guide</a>@endpermission
            <a class="nav-link {{ request()->routeIs('student.community-projects.*') ? 'active' : '' }}" href="{{ route('student.community-projects.index') }}"><span class="nav-icon">⌂</span> Community Projects</a>
            <a class="nav-link {{ request()->routeIs('student.project-tasks.*') ? 'active' : '' }}" href="{{ route('student.project-tasks.index') }}"><span class="nav-icon">✓</span> My Project Tasks</a>
            @permission('learning.assessments')<a class="nav-link {{ request()->routeIs('student.assessments.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'assessments') }}" data-student-tour="assessments"><span class="nav-icon">✓</span> Assessments @php($assessmentNavCount = $sidebarNotificationCounts['assessments'] ?: $sidebarPendingAssessmentCount) @if($assessmentNavCount > 0)<span class="nav-count" data-assessment-notification-count="{{ $sidebarNotificationCounts['assessments'] }}" data-pending-assessment-count="{{ $sidebarPendingAssessmentCount }}" aria-label="{{ $sidebarNotificationCounts['assessments'] ? $sidebarNotificationCounts['assessments'].' unread assessment notifications' : $sidebarPendingAssessmentCount.' pending assessments' }}">{{ $assessmentNavCount > 99 ? '99+' : $assessmentNavCount }}</span>@endif</a>@endpermission
            <p class="nav-label">Progress & Records</p>
            @permission('learning.grades')<a class="nav-link {{ request()->routeIs('student.grades.*') ? 'active' : '' }}" href="{{ route('student.grades.index') }}" data-student-tour="grades"><span class="nav-icon">◎</span> Grades</a>@endpermission
            @permission('reports.view')<a class="nav-link {{ request()->routeIs('student.reports.*') ? 'active' : '' }}" href="{{ route('student.reports.index') }}" data-student-tour="reports"><span class="nav-icon">▤</span> My Reports</a>@endpermission

            <p class="nav-label">Communication</p>
            @permission('communication.announcements')<a class="nav-link {{ request()->routeIs('student.announcements.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'announcements') }}" data-student-tour="announcements"><span class="nav-icon">◫</span> Announcements @if($sidebarNotificationCounts['announcements'] > 0)<span class="nav-count" data-announcement-notification-count="{{ $sidebarNotificationCounts['announcements'] }}" aria-label="{{ $sidebarNotificationCounts['announcements'] }} unread announcements">{{ $sidebarNotificationCounts['announcements'] > 99 ? '99+' : $sidebarNotificationCounts['announcements'] }}</span>@endif</a>@endpermission
            @permission('communication.messages')<a class="nav-link {{ request()->routeIs('student.messages.*') ? 'active' : '' }}" href="{{ route('notifications.categories.open', 'messages') }}" data-student-tour="messages"><span class="nav-icon">◇</span> Messages @if($sidebarUnreadMessageCount > 0)<span class="nav-count" data-unread-message-count="{{ $sidebarUnreadMessageCount }}" aria-label="{{ $sidebarUnreadMessageCount }} unread messages">{{ $sidebarUnreadMessageCount > 99 ? '99+' : $sidebarUnreadMessageCount }}</span>@endif</a>@endpermission
            @permission('ai.use')<a class="nav-link {{ request()->routeIs('ai-assistant.*') ? 'active' : '' }}" href="{{ route('ai-assistant.index') }}" data-student-tour="ai"><span class="nav-icon">✦</span> AI Assistant</a>@endpermission

            <p class="nav-label">Account</p>
            @permission('student.profile')<a class="nav-link {{ request()->routeIs('student.profile.*') ? 'active' : '' }}" href="{{ route('student.profile.edit') }}" data-student-tour="profile"><span class="nav-icon">⚙</span> Profile &amp; Security</a>@endpermission
            <a class="nav-link {{ request()->routeIs('student.system-guide') ? 'active' : '' }}" href="{{ route('student.system-guide') }}" data-student-tour="guide"><span class="nav-icon">?</span> System Guide</a>
        </nav>

        <form method="POST" action="{{ route('logout') }}" class="logout-form">
            @csrf
            <button type="submit" class="nav-link logout"><span class="nav-icon">↪</span> Sign out</button>
        </form>
    </aside>
    <button class="sidebar-backdrop" type="button" aria-label="Close navigation" data-sidebar-backdrop hidden></button>

    <main class="main-content">
        <header class="topbar">
            <button class="menu-button" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Toggle navigation">☰</button>
            <div data-student-tour-page-title><small>TAU NSTP / Student</small><h1>@yield('page-title', 'Dashboard')</h1></div>
            <x-notification-bell />
            <x-theme-toggle />
            <button class="admin-tour-topbar-button" type="button" data-start-student-tour><span aria-hidden="true">?</span> Guided Tour</button>
            <div class="topbar-status"><span></span> System online</div>
        </header>
        @if(session('status'))<div class="alert success">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="alert danger">{{ $errors->first() }}</div>@endif
        @yield('content')
        <footer class="app-footer">© {{ date('Y') }} Tarlac Agricultural University · National Service Training Program</footer>
    </main>
</div>
@permission('ai.use')<x-ai-chat-widget />@endpermission
<script src="{{ asset('js/sidebar.js') }}"></script>
<script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/table-sort.js') }}?v={{ filemtime(public_path('js/table-sort.js')) }}"></script>
<script src="{{ asset('js/admin-tour.js') }}?v={{ filemtime(public_path('js/admin-tour.js')) }}"></script>
</body>
</html>
