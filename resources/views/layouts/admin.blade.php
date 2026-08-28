<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super Admin') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="admin-body">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('admin.dashboard') }}">
                <span class="brand-mark">SN</span>
                <span><strong>Smart NSTP</strong><small>Management Platform</small></span>
            </a>

            <div class="role-card">
                <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                <span><strong>{{ auth()->user()->name }}</strong><small>Super Administrator</small></span>
            </div>

            <nav class="main-nav" aria-label="Main navigation">
                <p class="nav-label">Overview</p>
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                    <span class="nav-icon">⌂</span> Dashboard
                </a>
                <p class="nav-label">Administration</p>
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                    <span class="nav-icon">♙</span> User Accounts
                </a>
                <a class="nav-link {{ request()->routeIs('admin.components.*') ? 'active' : '' }}" href="{{ route('admin.components.index') }}"><span class="nav-icon">◉</span> NSTP Components</a>
                <a class="nav-link {{ request()->routeIs('admin.sections.*') ? 'active' : '' }}" href="{{ route('admin.sections.index') }}"><span class="nav-icon">▦</span> Sections</a>
                <a class="nav-link {{ request()->routeIs('admin.sectioning.*') ? 'active' : '' }}" href="{{ route('admin.sectioning.index') }}"><span class="nav-icon">♙</span> Student Sectioning</a>
                <span class="nav-link disabled"><span class="nav-icon">◫</span> Reports <em>Soon</em></span>
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
                <button class="menu-button" type="button" aria-label="Toggle navigation" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                <div>
                    <small>Smart NSTP / Super Admin</small>
                    <h1>@yield('page-title', 'Dashboard')</h1>
                </div>
                <div class="topbar-status"><span></span> System online</div>
            </header>

            @if (session('status'))
                <div class="alert success" role="status">{{ session('status') }}</div>
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
</body>
</html>
