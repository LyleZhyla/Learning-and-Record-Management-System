@extends('layouts.admin')
@section('title', 'Account Protection')
@section('page-title', 'Account Protection')

@section('content')
<div class="page-actions settings-page-heading">
    <div><span class="eyebrow">System settings</span><h2>Account protection</h2><p>Manage the system-wide automatic logout policy for all user accounts.</p></div>
</div>

<section class="settings-security-grid">
    <div class="card settings-section-card">
        <div class="settings-section-heading"><span class="settings-section-number">01</span><div><span class="eyebrow">Session security</span><h3>Automatic logout timeout</h3><p>Set how long any account may remain inactive before the system signs it out.</p></div><span class="settings-clock">◷</span></div>
        <form method="POST" action="{{ route('admin.settings.update') }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="settings_section" value="security">
            <label class="field-group"><span>Inactive for</span><div class="timeout-input-wrap"><input type="number" name="inactivity_timeout_minutes" min="1" max="1440" value="{{ old('inactivity_timeout_minutes', $timeoutMinutes) }}" required><strong>minutes</strong></div></label>
            @error('inactivity_timeout_minutes')<small class="field-error">{{ $message }}</small>@enderror
            <div class="timeout-presets" aria-label="Suggested timeout values">@foreach([5, 15, 30, 60, 120, 480] as $minutes)<button type="button" data-timeout-value="{{ $minutes }}">{{ $minutes < 60 ? $minutes.' min' : ($minutes / 60).' hr' }}</button>@endforeach</div>
            <div class="settings-impact-note"><span>!</span><p><strong>Applies to all accounts</strong>Existing sessions use the new timeout on their next request.</p></div>
            <div class="form-actions"><button class="primary-button compact">Save account protection</button></div>
        </form>
    </div>
    <aside class="card settings-summary-card">
        <span class="eyebrow">Current policy</span><strong>{{ $timeoutMinutes }}</strong><h3>minutes of inactivity</h3><p>Every automatic logout is recorded in System Logs for audit purposes.</p>
        <dl><div><dt>Maximum</dt><dd>24 hours</dd></div><div><dt>Minimum</dt><dd>1 minute</dd></div><div><dt>Last updated by</dt><dd>{{ $securitySetting?->updater?->name ?? 'System default' }}</dd></div><div><dt>Last updated</dt><dd>{{ $securitySetting?->updated_at?->format('M d, Y g:i A') ?? 'Initial setup' }}</dd></div></dl>
        <a class="secondary-outline-button" href="{{ route('admin.system-logs.index', ['action' => 'inactivity_logout']) }}">View automatic logouts</a>
    </aside>
</section>

<script src="{{ asset('js/system-settings.js') }}?v={{ filemtime(public_path('js/system-settings.js')) }}"></script>
@endsection
