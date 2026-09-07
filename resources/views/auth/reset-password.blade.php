<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reset Password · {{ config('app.name') }}</title>
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
                    <h1>Choose a new password</h1>
                    <p>Create a strong password you have not used for this account before.</p>
                </div>

                @if ($errors->any())
                    <div class="alert danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('password.store') }}" class="auth-form" data-password-rules>
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="email" required>

                    <label for="password">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{12,}" required>

                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

                    <x-password-requirements />
                    <button class="primary-button auth-submit-spaced" type="submit" disabled>Reset password <span>→</span></button>
                </form>

                <p class="auth-register-link"><a href="{{ route('login') }}">← Back to sign in</a></p>
            </div>
        </section>

        <section class="auth-visual" aria-label="Secure password reset">
            <div class="visual-glow glow-one"></div><div class="visual-glow glow-two"></div>
            <div class="auth-orbit orbit-one"></div><div class="auth-orbit orbit-two"></div>
            <div class="visual-content">
                <span class="visual-kicker">Secure account recovery</span>
                <h2>Protect your account with a stronger password.</h2>
                <p>Your new password must contain at least 12 characters, including uppercase and lowercase letters, a number, and a symbol.</p>
            </div>
        </section>
    </main>
    <script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>
