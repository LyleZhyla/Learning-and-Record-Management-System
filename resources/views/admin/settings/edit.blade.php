@extends('layouts.admin')
@section('title', 'Global Configuration')
@section('page-title', 'Global Configuration')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/system-settings-reset.css') }}?v={{ filemtime(public_path('css/system-settings-reset.css')) }}">
    <style>
        .settings-section-nav-three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        @media (max-width: 900px) { .settings-section-nav-three { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .settings-section-nav-three { grid-template-columns: 1fr; } }
    </style>
@endpush

@section('content')
<div class="page-actions settings-page-heading">
    <div><span class="eyebrow">System settings</span><h2>Global system configuration</h2><p>Manage settings that apply across every portal. Module-specific options remain inside their respective modules.</p></div>
</div>

<nav class="settings-section-nav settings-section-nav-three" aria-label="Global configuration sections">
    <a href="#branding-colors"><span>01</span><strong>Branding &amp; Colors</strong><small>Institution identity and system palette</small></a>
    <a href="#security"><span>02</span><strong>Security</strong><small>Automatic logout policy</small></a>
    <a href="{{ route('admin.landing-page.edit') }}"><span>03</span><strong>Landing Page</strong><small>Public website content</small></a>
</nav>

<div class="settings-section-stack">
    <section class="card settings-section-card" id="branding-colors">
        <div class="settings-section-heading"><span class="settings-section-number">01</span><div><span class="eyebrow">System appearance</span><h3>Branding & system colors</h3><p>These names and colors are shared by every account portal and public page.</p></div></div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_section" value="branding">
            <div class="form-grid">
                <label class="field-group full"><span>University / organization name</span><input name="organization_name" value="{{ old('settings_section') === 'branding' ? old('organization_name') : $branding['organization_name'] }}" maxlength="150" required></label>
                <label class="field-group"><span>Organization short name</span><input name="organization_short_name" value="{{ old('settings_section') === 'branding' ? old('organization_short_name') : $branding['organization_short_name'] }}" maxlength="20" required></label>
                <label class="field-group"><span>Program short name</span><input name="program_short_name" value="{{ old('settings_section') === 'branding' ? old('program_short_name') : $branding['program_short_name'] }}" maxlength="20" required></label>
                <label class="field-group full"><span>Program name</span><input name="program_name" value="{{ old('settings_section') === 'branding' ? old('program_name') : $branding['program_name'] }}" maxlength="150" required></label>
                <label class="field-group full"><span>Platform name</span><input name="platform_name" value="{{ old('settings_section') === 'branding' ? old('platform_name') : $branding['platform_name'] }}" maxlength="180" required></label>
                <label class="field-group full"><span>Official website</span><input type="url" name="organization_website" value="{{ old('settings_section') === 'branding' ? old('organization_website') : $branding['organization_website'] }}" maxlength="255" placeholder="https://example.edu.ph"></label>
            </div>

            <div class="system-color-editor">
                <div><strong>System color palette</strong><p>Applied to navigation, buttons, links, highlights, and status accents.</p></div>
                <div class="system-color-grid">
                    @foreach([
                        'system_primary_color' => ['Primary', 'Buttons and active controls', $systemColors['primary_color']],
                        'system_secondary_color' => ['Secondary', 'Sidebar and deep surfaces', $systemColors['secondary_color']],
                        'system_accent_color' => ['Accent', 'Success and highlights', $systemColors['accent_color']],
                    ] as $field => [$label, $help, $value])
                        <label class="system-color-field"><input type="color" name="{{ $field }}" value="{{ old('settings_section') === 'branding' ? old($field, $value) : $value }}" required><span><strong>{{ $label }}</strong><small>{{ $help }}</small></span></label>
                    @endforeach
                </div>
                <div class="system-color-preview" style="--system-preview-primary:{{ old('system_primary_color', $systemColors['primary_color']) }};--system-preview-secondary:{{ old('system_secondary_color', $systemColors['secondary_color']) }};--system-preview-accent:{{ old('system_accent_color', $systemColors['accent_color']) }}" data-system-color-preview><span></span><strong>System palette preview</strong><button type="button">Primary action</button><i>Accent</i></div>
            </div>

            <aside class="protected-brand-assets"><span aria-hidden="true">◆</span><div><strong>Protected brand assets</strong><p>SNAPIE characters, the official system logo lockup, and copyright text are fixed and cannot be replaced from this page.</p></div></aside>
            <div class="form-actions"><button class="primary-button compact">Save branding & colors</button></div>
        </form>
    </section>

    <section class="settings-security-grid" id="security">
        <div class="card settings-section-card">
            <div class="settings-section-heading"><span class="settings-section-number">02</span><div><span class="eyebrow">Account protection</span><h3>Automatic logout timeout</h3><p>Set how long any account may remain inactive before the system signs it out.</p></div><span class="settings-clock">◷</span></div>
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="settings_section" value="security">
                <label class="field-group"><span>Inactive for</span><div class="timeout-input-wrap"><input type="number" name="inactivity_timeout_minutes" min="1" max="1440" value="{{ old('settings_section') === 'security' ? old('inactivity_timeout_minutes') : $timeoutMinutes }}" required><strong>minutes</strong></div></label>
                @error('inactivity_timeout_minutes')<small class="field-error">{{ $message }}</small>@enderror
                <div class="timeout-presets" aria-label="Suggested timeout values">@foreach([5, 15, 30, 60, 120, 480] as $minutes)<button type="button" data-timeout-value="{{ $minutes }}">{{ $minutes < 60 ? $minutes.' min' : ($minutes / 60).' hr' }}</button>@endforeach</div>
                <div class="settings-impact-note"><span>!</span><p><strong>Applies to all accounts</strong>Existing sessions use the new timeout on their next request.</p></div>
                <div class="form-actions"><button class="primary-button compact">Save security timeout</button></div>
            </form>
        </div>
        <aside class="card settings-summary-card">
            <span class="eyebrow">Current policy</span><strong>{{ $timeoutMinutes }}</strong><h3>minutes of inactivity</h3><p>Every automatic logout is recorded in System Logs for audit purposes.</p>
            <dl><div><dt>Maximum</dt><dd>24 hours</dd></div><div><dt>Minimum</dt><dd>1 minute</dd></div><div><dt>Last updated by</dt><dd>{{ $securitySetting?->updater?->name ?? 'System default' }}</dd></div><div><dt>Last updated</dt><dd>{{ $securitySetting?->updated_at?->format('M d, Y g:i A') ?? 'Initial setup' }}</dd></div></dl>
            <a class="secondary-outline-button" href="{{ route('admin.system-logs.index', ['action' => 'inactivity_logout']) }}">View automatic logouts</a>
        </aside>
    </section>

    <section class="card system-settings-reset-card">
        <div class="system-settings-reset-icon" aria-hidden="true">↺</div>
        <div class="system-settings-reset-copy">
            <span class="eyebrow">Recovery option</span>
            <h3>Reset branding to default settings</h3>
            <p>Restore the original institutional names, website, and system color palette. Accounts, registrations, and all module data will remain unchanged.</p>
        </div>
        <form method="POST" action="{{ route('admin.settings.reset-defaults') }}" onsubmit="return confirm('Reset branding and system colors to their default settings? Accounts and module data will not be changed.');">
            @csrf
            <button class="system-settings-reset-button" type="submit">Reset branding</button>
        </form>
    </section>
</div>
<script src="{{ asset('js/system-settings.js') }}?v={{ filemtime(public_path('js/system-settings.js')) }}"></script>
@endsection
