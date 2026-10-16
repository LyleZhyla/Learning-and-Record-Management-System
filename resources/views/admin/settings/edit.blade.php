@extends('layouts.admin')
@section('title', 'Global Configuration')
@section('page-title', 'Global Configuration')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/system-settings-reset.css') }}?v={{ filemtime(public_path('css/system-settings-reset.css')) }}">
@endpush

@section('content')
<div class="page-actions settings-page-heading">
    <div><span class="eyebrow">System settings</span><h2>Branding configuration</h2><p>Update the institution identity and color palette shared across every portal and public page.</p></div>
</div>

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
