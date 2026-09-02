<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Account Sign In · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <x-theme-init />
</head>
<body class="auth-body">
    <x-theme-toggle />
    <main class="auth-shell auth-animated">
        <section class="auth-panel">
            <div class="auth-form-wrap">
                <a class="brand auth-brand" href="{{ url('/') }}">
                    <img class="brand-landscape theme-logo-light" src="{{ asset('images/snapie-landscape-light.png') }}" alt="SNAPIE — Smart NSTP Management and AI-Integrated Platform">
                    <img class="brand-landscape theme-logo-dark" src="{{ asset('images/snapie-landscape-dark.png') }}" alt="SNAPIE — Smart NSTP Management and AI-Integrated Platform">
                </a>

                <div class="auth-heading">
                    <span class="eyebrow">Secure NSTP portal</span>
                    <h1>Welcome back</h1>
                    <p>Sign in using your authorized Smart NSTP account.</p>
                </div>

                @if (session('status'))
                    <div class="alert success" role="status">{{ session('status') }}</div>
                @endif

                @if (session('inactivity_timeout'))
                    <div class="alert warning" role="status">{{ session('inactivity_timeout') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="auth-form">
                    @csrf
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required placeholder="name@smartnstp.local">

                    <div class="label-row">
                        <label for="password">Password</label>
                        <span>Minimum 12 characters</span>
                    </div>
                    <div class="password-input-wrap">
                        <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Enter your password" data-login-password>
                        <button class="password-visibility-toggle" type="button" aria-label="Show password" aria-pressed="false" title="Show password" data-password-visibility-toggle>
                            <svg data-eye-open viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg>
                            <svg data-eye-closed viewBox="0 0 24 24" aria-hidden="true" hidden><path d="m4 4 16 16M9.9 6.3A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a15.6 15.6 0 0 1-2.3 3M6.2 7.2A15.8 15.8 0 0 0 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 3-.5M10.2 10.2a2.8 2.8 0 0 0 3.6 3.6"/></svg>
                        </button>
                    </div>

                    <label class="check-row" for="remember">
                        <input id="remember" name="remember" type="checkbox" value="1">
                        <span>Keep me signed in on this device</span>
                    </label>

                    <button class="primary-button" type="submit">Sign in to dashboard <span>→</span></button>
                </form>

                <p class="auth-register-link">New student? <a href="{{ route('register') }}">Complete your registration</a></p>

                <p class="security-note">Protected by server-side sessions, CSRF validation, password hashing, and login rate limiting.</p>
            </div>
        </section>

        <section class="auth-visual" aria-label="Smart NSTP system introduction">
            <div class="visual-glow glow-one"></div>
            <div class="visual-glow glow-two"></div>
            <div class="auth-orbit orbit-one"></div>
            <div class="auth-orbit orbit-two"></div>
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
    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/login-password-toggle.js') }}?v={{ filemtime(public_path('js/login-password-toggle.js')) }}"></script>
</body>
</html>
