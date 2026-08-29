<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Student Account · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/snapie-logo-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="auth-body">
    <main class="auth-shell auth-animated auth-register">
        <section class="auth-panel">
            <div class="auth-form-wrap register-form-wrap">
                <a class="brand auth-brand" href="{{ route('login') }}" data-auth-direction="login">
                    <img class="brand-landscape" src="{{ asset('images/snapie-landscape.png') }}" alt="SNAPIE — Smart NSTP Management and AI-Integrated Platform">
                </a>

                <div class="auth-heading">
                    <span class="eyebrow">Student registration</span>
                    <h1>Create your account</h1>
                    <p>Register for secure access to attendance, learning materials, assessments, and grades.</p>
                </div>

                @if ($errors->any())
                    <div class="alert danger" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('register.store') }}" class="auth-form">
                    @csrf
                    <label for="name">Full name</label>
                    <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" autofocus required maxlength="100" placeholder="Enter your complete name">

                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required placeholder="you@example.com">

                    <div class="label-row"><label for="password">Password</label><span>12+ characters</span></div>
                    <input id="password" name="password" type="password" autocomplete="new-password" required placeholder="Uppercase, lowercase, number, symbol">

                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Enter your password again">

                    <div class="registration-note"><span>✓</span><p>This form creates a <strong>Student account only</strong>. Your unique attendance QR is generated automatically.</p></div>
                    <button class="primary-button" type="submit">Create student account <span>→</span></button>
                </form>

                <p class="auth-switch">Already registered? <a href="{{ route('login') }}" data-auth-direction="login">Sign in to SNAPIE</a></p>
            </div>
        </section>

        <section class="auth-visual" aria-label="Student registration introduction">
            <div class="visual-glow glow-one"></div>
            <div class="visual-glow glow-two"></div>
            <div class="auth-orbit orbit-one"></div>
            <div class="auth-orbit orbit-two"></div>
            <div class="visual-content">
                <span class="visual-kicker">Your NSTP journey starts here</span>
                <h2>One account for every learning milestone.</h2>
                <p>Receive your permanent attendance QR, open course resources, submit classwork, and follow your progress from a single student workspace.</p>
                <div class="registration-feature-list">
                    <div><span>01</span><p><strong>Personal QR</strong><small>Generated automatically and unique to your account.</small></p></div>
                    <div><span>02</span><p><strong>Connected classwork</strong><small>Materials, assessments, and feedback in one place.</small></p></div>
                    <div><span>03</span><p><strong>Progress visibility</strong><small>Attendance history and computed grades when available.</small></p></div>
                </div>
            </div>
        </section>
    </main>
    <script src="{{ asset('js/auth-transition.js') }}?v={{ filemtime(public_path('js/auth-transition.js')) }}"></script>
</body>
</html>
