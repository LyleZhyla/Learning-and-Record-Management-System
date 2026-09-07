<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password · {{ config('app.name') }}</title>
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
                    <span class="eyebrow">Account recovery</span>
                    <h1>Forgot your password?</h1>
                    <p>Enter your account email and we will send you a secure link to choose a new password.</p>
                </div>

                @if (session('status'))
                    <div class="alert success" role="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('password.email') }}" class="auth-form">
                    @csrf
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required placeholder="name@smartnstp.local">
                    <button class="primary-button auth-submit-spaced" type="submit">Send reset link <span>→</span></button>
                </form>

                <p class="auth-register-link"><a href="{{ route('login') }}">← Back to sign in</a></p>
                <p class="security-note">For your security, reset links expire after 60 minutes and can only be used once.</p>
            </div>
        </section>

        <section class="auth-visual" aria-label="Secure account recovery">
            <div class="visual-glow glow-one"></div><div class="visual-glow glow-two"></div>
            <div class="auth-orbit orbit-one"></div><div class="auth-orbit orbit-two"></div>
            <div class="visual-content">
                <span class="visual-kicker">Secure account recovery</span>
                <h2>Get back to your NSTP workspace safely.</h2>
                <p>We protect every reset request with short-lived, single-use tokens and request rate limiting.</p>
            </div>
        </section>
    </main>
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
