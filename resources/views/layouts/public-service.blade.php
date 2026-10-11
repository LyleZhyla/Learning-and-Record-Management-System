@php($branding = \App\Models\SystemSetting::branding())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#10264b">
    <title>@yield('title', $branding['program_short_name'].' Services') | {{ $branding['organization_name'] }}</title>
    <link rel="icon" href="{{ \App\Models\SystemSetting::brandingLogoUrl('organization') }}">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/service-requests.css') }}?v={{ filemtime(public_path('css/service-requests.css')) }}">
    @stack('head')
</head>
<body class="service-request-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="request-header">
        <div class="page-shell request-nav">
            <a class="brand" href="{{ route('landing') }}" aria-label="Return to Smart NSTP home">
                <img src="{{ \App\Models\SystemSetting::brandingLogoUrl('organization') }}" alt="{{ $branding['organization_name'] }} logo">
                <span class="brand-copy"><strong>{{ $branding['organization_name'] }}</strong><small>{{ $branding['program_name'] }}</small></span>
            </a>
            <div class="request-nav-actions"><a href="{{ route('landing') }}#services">← Back to services</a><a class="request-portal-link" href="{{ route('login') }}">Student Portal →</a></div>
        </div>
    </header>
    <main id="main-content">@yield('content')</main>
    <footer class="request-footer"><div class="page-shell"><span>© LZPatalod 2026</span><a href="{{ route('landing') }}">{{ $branding['platform_name'] }} home</a></div></footer>
    @stack('scripts')
</body>
</html>
