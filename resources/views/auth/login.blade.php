<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Super Admin Sign In · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="auth-body">
    <main class="auth-shell">
        <section class="auth-panel">
            <div class="auth-form-wrap">
                <a class="brand auth-brand" href="{{ url('/') }}">
                    <span class="brand-mark">SN</span>
                    <span><strong>Smart NSTP</strong><small>Management Platform</small></span>
                </a>

                <div class="auth-heading">
                    <span class="eyebrow">Secure administration portal</span>
                    <h1>Welcome back</h1>
                    <p>Sign in using your authorized Super Administrator account.</p>
                </div>

                @if (session('status'))
                    <div class="alert success" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required placeholder="superadmin@smartnstp.local">

                    <div class="label-row">
                        <label for="password">Password</label>
                        <span>Minimum 12 characters</span>
                    </div>
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password">

                    <label class="check-row" for="remember">
                        <input id="remember" name="remember" type="checkbox" value="1">
                        <span>Keep me signed in on this device</span>
                    </label>

                    <button class="primary-button" type="submit">Sign in to dashboard <span>→</span></button>
                </form>

                <p class="security-note">Protected by server-side sessions, CSRF validation, password hashing, and login rate limiting.</p>
            </div>
        </section>

        <section class="auth-visual" aria-label="Smart NSTP system introduction">
            <div class="visual-glow glow-one"></div>
            <div class="visual-glow glow-two"></div>
            <div class="visual-content">
                <span class="visual-kicker">One connected NSTP ecosystem</span>
                <h2>Lead every component from one secure workspace.</h2>
                <p>Manage CWTS, LTS, and ROTC operations while keeping student records, attendance, learning, and reports organized.</p>
                <div class="component-grid">
                    <div><strong>CWTS</strong><span>Civic Welfare</span></div>
                    <div><strong>LTS</strong><span>Literacy Training</span></div>
                    <div><strong>ROTC</strong><span>Reserve Officers</span></div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
