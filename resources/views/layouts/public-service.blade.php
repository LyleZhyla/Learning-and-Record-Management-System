<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#10264b">
    <title>@yield('title', 'NSTP Services') | Tarlac Agricultural University</title>
    <link rel="icon" href="{{ asset('images/branding/tau-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/service-requests.css') }}?v={{ filemtime(public_path('css/service-requests.css')) }}">
    @stack('head')
</head>
<body class="service-request-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="request-header">
        <div class="page-shell request-nav">
            <a class="brand" href="{{ route('landing') }}" aria-label="Return to Smart NSTP home">
                <img src="{{ asset('images/branding/tau-logo.png') }}" alt="Tarlac Agricultural University seal">
                <span class="brand-copy"><strong>Tarlac Agricultural University</strong><small>National Service Training Program</small></span>
            </a>
            <div class="request-nav-actions"><a href="{{ route('landing') }}#services">← Back to services</a><a class="request-portal-link" href="{{ route('login') }}">Student Portal →</a></div>
        </div>
    </header>
    <main id="main-content">@yield('content')</main>
    <footer class="request-footer"><div class="page-shell"><span>© {{ date('Y') }} Tarlac Agricultural University · National Service Training Program</span><a href="{{ route('landing') }}">Smart NSTP home</a></div></footer>
    @stack('scripts')
</body>
</html>
